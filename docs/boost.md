# Laravel Boost and agent skills

This package ships guidance for agents working in a **consuming Laravel application**. Contributors changing the package itself should use the [contributor agent workflow](development/agent-workflow.md).

## Install in an application

Install the package as a direct Composer dependency, then install Boost as a development dependency if the application does not already use it:

```sh
composer config repositories.table-builder vcs https://github.com/wtroiano86/table-builder.git
composer require wtroiano/table-builder:^1.0
composer require laravel/boost --dev
php artisan boost:install
```

In Boost's interactive installer, select your agents and enable guidelines and skills. Select this package's guidance and skills when prompted. Review the generated files with your application's changes. Boost is optional: the table package runs without it, and does not add it as a runtime dependency.

For an application that already uses Boost, run the interactive updater after adding or upgrading this package:

```sh
php artisan boost:update
```

Allow discovery and select the new package resources. A non-interactive update may preserve the existing selection without opting into newly installed packages. `--no-discover` updates existing resources without discovering new ones. Agent support and destination directories are owned by your installed Boost version; follow its prompts rather than copying into a guessed global directory.

## Shipped resources

| Resource | Purpose |
| --- | --- |
| [Core guideline](../resources/boost/guidelines/core.blade.php) | Namespaces, supported integration paths, authorization and state boundaries |
| [table-builder-development](../resources/boost/skills/table-builder-development/SKILL.md) | Build PHP tables, columns, queries and registry factories |
| [table-builder-react](../resources/boost/skills/table-builder-react/SKILL.md) | Install and customize the supplied React table/page |
| [table-builder-actions-exports](../resources/boost/skills/table-builder-actions-exports/SKILL.md) | Implement actions, downloads and tenant-aware queued exports |

Boost discovers the guideline under `resources/boost/guidelines/core.blade.php` and skills under `resources/boost/skills/<skill-name>/SKILL.md` in the installed Composer package. Each skill has YAML `name` and `description` metadata. The queue-context reference is nested inside its skill so it is copied with the skill. No service-provider registration or `vendor:publish` command is needed for these agent resources. React assets still use their own publish tags.

The skills point to documentation under `vendor/wtroiano/table-builder/docs/`, relative to the application's root. If the application uses a custom Composer vendor directory, adjust that path when reading the docs. Agents can read these files locally; this does not assert that Boost's documentation search indexes this package.

## Application conventions and upgrades

Keep app-specific model, policy, tenancy and UI conventions in the application's own instructions and `.ai/guidelines` or `.ai/skills` resources. Do not edit files inside `vendor`; Composer replaces them during updates. Use Boost's custom-resource mechanism for overrides, and review regenerated agent files after `boost:update`. Application instructions take precedence over these package defaults.

Useful agent requests include:

- “Use table-builder-development to build a users table with a scoped resource and sortable name.”
- “Use table-builder-react to embed the published table in our existing layout.”
- “Use table-builder-actions-exports to add a policy-checked bulk action and queued tenant export.”

The discovery paths and metadata follow the official [third-party package guidelines](https://laravel.com/framework/docs/13.x/boost#third-party-package-guidelines) and [package skills](https://laravel.com/framework/docs/13.x/boost#third-party-package-skills) conventions. Discovery, Blade rendering, skill metadata parsing and copying of the nested reference were smoke-tested against released Laravel Boost **2.10.3** with this package installed as a direct path dependency. This validates the shipped resources; it does not test every agent integration or install agent configuration globally.
