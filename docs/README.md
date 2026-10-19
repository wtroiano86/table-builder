# Table Builder documentation

The package provides Eloquent-backed table definitions, action/export endpoints, and a React table component with an optional Inertia page.

## Consumers

Start with [Getting started](consumers/getting-started.md) to render a table in a Laravel/Inertia/React application.

| Guide | Use it for |
| --- | --- |
| [Defining tables](consumers/tables.md) | Fluent builders, subclasses, registry factories, configuration and row hooks |
| [Columns](consumers/columns.md) | Values, types, formatting, relationships, links, images and export configuration |
| [React component and page](consumers/react.md) | Publishing the view, embedding it, props, custom cells and themes |
| [Search, state and pagination](consumers/state-and-pagination.md) | Named URL state, defaults, visibility and full/simple/cursor pagination |
| [Actions and authorization](consumers/actions.md) | Page access, row/bulk actions, eligibility, confirmations and lifecycle hooks |
| [Exports and queue context](consumers/exports.md) | Downloads, selected/filtered exports, custom exporters and tenant-aware workers |
| [HTTP and payload contract](consumers/http-contract.md) | Custom renderers, request fields, response shapes and errors |
| [Troubleshooting](consumers/troubleshooting.md) | Installation, policy, query, selection and worker issues |

## Package developers

Contributors changing this repository should read [Contributing](development/contributing.md), [Testing and quality checks](development/testing.md), [Architecture and namespaces](architecture.md), and [Releasing](development/releasing.md). These commands and internal extension points differ from application integration.

## Agentic development

[Laravel Boost integration](boost.md) explains how consumers install the package's guidance and skills. [Agent workflow for contributors](development/agent-workflow.md) explains how to change and verify the package itself. The repository's [AGENTS.md](https://github.com/wtroiano86/table-builder/blob/main/AGENTS.md) remains its canonical agent instruction file.

See [LICENSE](../LICENSE) for licensing.
