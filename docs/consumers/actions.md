# Actions and authorization

The package registers `POST /table-builder/actions` and `POST /table-builder/exports`. Their route names are `table-builder.actions.execute` and `table-builder.exports.execute`.

Both endpoints use `['web', 'auth']` by default. Configure `table-builder.middleware` to use the application's guard or additional role/tenant middleware. Refresh Laravel's route cache after changing this setting. Each endpoint also calls the resolved table's `authorize(Request $request)` before resolving actions or exports. Its default permits authenticated users; override it for table-specific [policy checks](tables.md). Protect the table's page route separately.

For intentionally public tables, both the configured middleware and the table authorization method must permit guests. Action and export permissions still apply.

## Define actions

Use a [registered factory](tables.md#registered-fluent-tables) for fluent tables, or return definitions from a table subclass. `authorize()` takes a boolean; evaluate request-aware policy checks inside a lazy `actions(fn ($table) => [...])` callback when using a factory.

Add an `actions()` method to the table:

```php class-body
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Wtroiano\TableBuilder\Actions\Action;

public function actions(): array
{
    return [
        Action::make('Deactivate')->key('deactivate')
            ->authorize(Gate::allows('updateAny', User::class))
            ->asBulkAction(chunkSize: 100)
            ->collectResults(false)
            ->confirm('Deactivate users?', 'Selected users will lose access.', 'Deactivate', 'Cancel')
            ->handle(function (User $user): void {
                Gate::authorize('update', $user);
                $user->update(['active' => false]);
            }),
    ];
}
```

This example assumes an application `User` model with an `active` attribute and `updateAny`/`update` policies. `asBulkAction()` displays an action on rows and in the header's **Actions** menu beside Columns and Export; `onlyAsBulkAction()` displays it only in that menu. Bulk actions are disabled until eligible rows are selected. Use stable keys so labels can change without breaking clients. The view can render an Actions column automatically; add `ActionColumn::make()` to the columns to control its placement, or `ActionColumn::make()->asDropdown()` for a row menu, importing `Wtroiano\TableBuilder\Columns\ActionColumn`.

For compact outlined row buttons, configure `->icon('user-minus')->hideLabel()->variant('outline')->variantColor('amber')` on the action and supply React's `renderIcon` callback for that icon name. Use `red` for destructive actions. Icon buttons retain accessible labels and use the action label as their tooltip unless `tooltip()` is set. Menu entries always show their labels, and row buttons keep text when no icon renderer is available.

For navigation instead of a server handler:

```php
use App\Models\User;
use Wtroiano\TableBuilder\Actions\Action;

$action = Action::make('Edit')->key('edit')
    ->url(fn (User $user): string => '/users/'.$user->getKey().'/edit');
```

A definition with a URL serializes as `link`; a handler serializes as `action`; a definition with neither is `custom` and needs React's `onCustomAction`. `disabled()` and `hidden()` take booleans. Record-specific permissions belong in the handler, while `selectableUsing()` or `isSelectable()` can express table-wide row eligibility. `icon()`, `tooltip()`, `variant()` and `variantColor()` provide presentation metadata; use `renderIcon()` for application icons.

## Command execution

Post selected raw keys to the action endpoint:

```json
{"table":"App\\Tables\\UsersTable","action":"deactivate","selectionMode":"page","keys":[1,2]}
```

`selectionMode: "all"` streams the table's base resource query in primary-key chunks; it does not apply client search state. Use a scoped `query()` to define the eligible dataset. Resource queries with limits or offsets capture eligible primary keys before handlers run, preserving membership when handlers change sorting values or delete records. Those queries retain the captured key list in memory; unrestricted queries continue to stream without it. The optional `chunk` strategy uses offset pagination and needs care when handlers delete or change matching records.

Malformed inputs return 422; unknown actions or empty selections return 404; denied table/action access returns 403. Unauthenticated JSON requests return 401. Success responses contain `ok`, `status`, `action`, `processed`, `skipped`, and `results`. Inertia requests redirect with `table_action` session data; execution failures default to `Action failed.` and JSON status 500. Handler validation exceptions retain Laravel's normal field errors: JSON responses use status 422, and Inertia requests redirect with validation errors.

Globally denied actions never run lifecycle hooks. For authorized actions, `before($models, $action)` and `after($models, $results, $action)` receive the selection, including records skipped by `isSelectable`, `disabled`, or `hidden`. Hooks run once for explicit selections and per chunk for select-all. Handlers must enforce any action-specific per-record policy. Partial failures preserve processed/skipped counts; no automatic transaction or rollback is applied.
