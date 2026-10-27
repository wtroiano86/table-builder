# Tenant-aware queued fluent tables

Adapt this recipe to the application's existing tenant resolver. It assumes `App\Models\User` has `tenant_id` and a `viewAny` policy, and that trusted middleware puts an integer tenant ID into Laravel's `Context`. Context is captured server-side, never accepted directly as a client-selected tenant.

Inside an application provider's `boot()` method, register a reconstructible factory:

```php
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\TableBuilder;
use Wtroiano\TableBuilder\TableRegistry;

TableRegistry::define('tenant-users', static fn (): TableBuilder => TableBuilder::make('users')
    ->authorize(fn (Request $request): bool => $request->user()?->can('viewAny', User::class) ?? false)
    ->resource(static function (): Builder {
        $tenantId = Context::get('tenant_id');
        abort_unless(is_int($tenantId), 403);

        return User::query()->where('tenant_id', $tenantId);
    })
    ->columns([TextColumn::make('name')->sortable()->searchable()])
    ->exports([Export::make('Users')->filename('users.xlsx')->key('users')->queue('users.xlsx', 'local')])
    ->exportContext(
        capture: static fn (ExportRequest $request): array => ['tenant_id' => Context::get('tenant_id')],
        restore: static function (array $context, Closure $callback): mixed {
            abort_unless(is_int($context['tenant_id'] ?? null), 403);

            return Context::scope($callback, $context);
        },
    ));
```

The factory is available in web and worker boot. Its resource resolves after context restoration instead of capturing a live HTTP-scoped query. `Context::scope()` releases the restored context even on exceptions. The package restores captured context around Excel callbacks, queued execution and queued sheet events.

If the application uses a tenancy service or authentication-dependent scope, restore those services in the hook with `try/finally` cleanup. Capture only the trusted identity needed to reestablish context. Validate that it is still usable under the application's queue policy; never fall back to an unscoped query when missing. Do not serialize requests, credentials, closures or Eloquent builders as context.

Class tables implement `exportContext(ExportRequest): array` and `withExportContext(array, Closure): mixed` instead of configuring the builder hooks. Their query and definition callbacks need the same lazy context discipline.

Test with a serialized export in a clean worker: tenant A cannot export tenant B, missing context fails closed, callback failures release state, and the next job sees no leftover tenant. Test authentication restoration too if any query or column callback depends on it.
