# Exports and queue context

Use a [registered fluent factory](tables.md#registered-fluent-tables) or a table subclass so the HTTP endpoint and worker can reconstruct the definition. This method fragment belongs in a table subclass:

```php class-body
use App\Models\User;
use Wtroiano\TableBuilder\Exports\Export;

public function exports(): array
{
    return [
        Export::make('Users', 'users.xlsx')->key('users')
            ->authorize(fn (): bool => auth()->user()?->can('viewAny', User::class) ?? false)
            ->limitToFilteredRows(),
    ];
}
```

Post to the export endpoint:

```json
{"table":"App\\Tables\\UsersTable","export":"users","state":{"search":"Ada","sort":"name","direction":"asc"},"keys":[]}
```

Use `limitToSelectedRows()` to restrict exports to `keys`; an empty selection then exports no rows. `queue('users.xlsx', 'local')` queues storage instead of downloading; `asDownload(false)` stores synchronously. `using($table, $export, $request, $query)` supplies a custom exporter; a non-null result ends execution. Query changes from a custom exporter returning null are retained by synchronous dispatch only. Unauthorized exports return 403 and unknown exports return 404.

Columns support `dontExport()`, `exportLabel()`, `exportAs()`, `exportFormat()`, and `exportStyle()`. String values are written as text; CSV values starting with formula-control characters are prefixed with an apostrophe. Numeric PHP values retain numeric types. To emit trusted formulas intentionally, opt in on that column with `exportFormulas()`. Never enable that option for untrusted input.

## Custom exporter

For example, return a JSON count instead of a workbook. The callback receives the prepared query, including configured filtered/selected restrictions:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Table;

$export = Export::make('Count users', 'users.json')->key('count')
    ->limitToFilteredRows()
    ->using(function (Table $table, Export $export, ExportRequest $request, Builder $query): JsonResponse {
        return response()->json(['count' => $query->count()]);
    });
```

The supplied view reports successful JSON completion; displaying a custom result such as this count belongs in `onExportSuccess`. Ordinary synchronous files use the built-in download handling. Queued/stored exports report that processing started; the application owns completion notifications and later file access.

## Queued tenant context

Workers do not inherit the HTTP request, logged-in user, or tenant resolver. Override both hooks for tables whose queries or column callbacks depend on that state. Capture trusted server-side context, validate it, and restore it with guaranteed cleanup. For example, when the application uses Laravel's `Context` to scope tenants:

```php class-body
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;

public function exportContext(ExportRequest $request): array
{
    return ['tenant_id' => Context::get('tenant_id')];
}

public function withExportContext(array $context, Closure $callback): mixed
{
    abort_unless(is_int($context['tenant_id'] ?? null), 403);
    return Context::scope($callback, $context);
}

public function query(): Builder
{
    $tenantId = Context::get('tenant_id');
    abort_unless(is_int($tenantId), 403);
    return parent::query()->where('tenant_id', $tenantId);
}
```

Context is serialized with the export, restored around Excel callbacks, event listeners (including queued `AfterSheet` events), and queue execution, and released afterward even if a callback throws. Use scalar/array context values, not requests, closures, credentials, or live query builders. Restore your application's authentication/tenant services in the hook if it does not use `Context`. Do not fall back to an unscoped query when context is absent. Legacy serialized exports remain readable, with an empty context.

Fluent tables configure the same hooks with `exportContext(capture: ..., restore: ...)`. In the registered builder chain, replace `resource(User::class)` with a lazy scoped resource and add the hooks:

```php chain
->resource(static function (): Builder {
    $tenantId = Context::get('tenant_id');
    abort_unless(is_int($tenantId), 403);
    return User::query()->where('tenant_id', $tenantId);
})
->exportContext(
    capture: static fn (ExportRequest $request): array => ['tenant_id' => Context::get('tenant_id')],
    restore: static function (array $context, Closure $callback): mixed {
        abort_unless(is_int($context['tenant_id'] ?? null), 403);
        return Context::scope($callback, $context);
    },
)
```
