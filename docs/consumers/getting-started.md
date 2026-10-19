# Getting started

Use this guide in a Laravel application already configured for Inertia and React. The package supplies the table; your application supplies models, policies, routes, authentication and the Vite build.

## Requirements

| Layer | Supported setup |
| --- | --- |
| PHP / Laravel | PHP 8.5 and Laravel 13 |
| Frontend | React 18 with Inertia 2, or React 19 with Inertia 2/3 |
| Build | The consuming application's Vite configuration |
| Data | An Eloquent model or Eloquent builder |

The package uses Laravel Excel for spreadsheet generation. Its Composer dependencies are installed with the package. The root npm manifest in this repository is for developing the package, not a separate npm dependency consumers need to install.

## Install

This package is distributed through GitHub and is not currently listed on Packagist. Register its Composer VCS repository in the application before installing:

```sh
composer config repositories.table-builder vcs https://github.com/wtroiano86/table-builder.git
composer require wtroiano/table-builder:^1.0
php artisan vendor:publish --tag=table-builder-react
php artisan vendor:publish --tag=table-builder-react-page
```

Laravel discovers the service provider automatically. The first publish tag copies TSX, CSS and types into `resources/js/vendor/table-builder`. The second copies a page into `resources/js/pages/vendor/table-builder.tsx`. If the application resolves pages from `resources/js/Pages`, move the page into `Pages/vendor/`; the import remains valid.

Publish the optional configuration when changing middleware, pagination defaults or generator paths:

```sh
php artisan vendor:publish --tag=table-builder-config
```

## Define and render a table

This example assumes an existing `App\Models\User` with `name` and `email` fields and an application policy implementing `viewAny`. Add this controller to the application:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Wtroiano\TableBuilder\Columns\TextColumn;
use Wtroiano\TableBuilder\TableBuilder;

final class UsersController
{
    public function index(Request $request): Response
    {
        $table = TableBuilder::make('users')
            ->resource(User::class)
            ->authorize(fn (Request $request): bool => $request->user()?->can('viewAny', User::class) ?? false)
            ->columns([
                TextColumn::make('name', sortable: true, searchable: true),
                TextColumn::make('email')->label('Email address'),
            ])
            ->defaultSort('name')
            ->perPage(15)
            ->build();

        abort_unless($table->authorize($request), 403);

        return Inertia::render('vendor/table-builder', [
            'table' => $table,
            'title' => 'Users',
            'description' => 'People in your organization.',
        ]);
    }
}
```

Add an authenticated page route in `routes/web.php`:

```php
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UsersController::class, 'index'])->middleware('auth');
```

Build the application's assets and visit `/users`:

```sh
npm run build
```

`TableBuilder::make('users')` gives this table a query namespace, so its search parameter is `users[search]`. The React page reads the serialized table payload and performs Inertia visits to the current page. The package registers action/export endpoints, while the application owns this page route and its access policy.

## Add actions or exports

Register a fluent factory in an application service provider before adding executable actions or exports. The later HTTP request and queue worker must be able to reconstruct the same table. See [Registered fluent tables](tables.md#registered-fluent-tables), [Actions](actions.md) and [Exports](exports.md).

Existing `Table` subclasses are also supported. To generate one instead of using a fluent builder:

```sh
php artisan make:table-builder UsersTable --model=User
```

## Use an existing page layout

Import `Table` into an application page and pass it the table prop. The [React guide](react.md) covers relative imports, generic row types, custom cells, callback hooks and themes.

If using Laravel Boost, follow [the Boost setup](../boost.md) to install this package's guidelines and skills for the application's agent.
