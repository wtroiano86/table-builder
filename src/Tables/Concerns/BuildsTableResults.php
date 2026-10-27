<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pagination\TablePaginationBuilder;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Tables\TablePayloadBuilder;

trait BuildsTableResults
{
    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $meta = [
            'columns' => $this->serializedColumnsDefinition(),
            'pagination' => $this->pagination()->toArray(),
            'queryString' => [
                'namespace' => $this->isNamed() ? $this->name() : null,
                'page' => $this->pageName(),
                'perPage' => $this->queryStringKey('perPage'),
                'cursor' => $this->cursorName(),
                'search' => $this->queryStringKey('search'),
                'sort' => $this->queryStringKey('sort'),
                'direction' => $this->queryStringKey('direction'),
                'columns' => $this->queryStringKey('columns'),
                'sticky' => $this->queryStringKey('sticky'),
            ],
            'table' => $this->reference(),
            'actionEndpoint' => route('table-builder.actions.execute', absolute: false),
            'exportEndpoint' => route('table-builder.exports.execute', absolute: false),
        ];

        if ($this->actionsDefinition() === [] && $this->exportsDefinition() === []) {
            unset($meta['table'], $meta['actionEndpoint'], $meta['exportEndpoint']);
        } elseif ($this->actionsDefinition() === []) {
            unset($meta['actionEndpoint']);
        } elseif ($this->exportsDefinition() === []) {
            unset($meta['exportEndpoint']);
        }

        return $meta;
    }

    /**
     * @return array{
     *     name: string,
     *     state: array{
     *         page: int,
     *         perPage: int,
     *         cursor: ?string,
     *         search: ?string,
     *         sort: ?string,
     *         direction: string
     *     },
     *     meta: array<string, mixed>,
     *     rowSelectionKey: ?string,
     *     selectable: bool,
     *     persistRowSelectionAcrossPages: bool
     * }
     */
    public function payload(?TableRequest $request = null): array
    {
        return new TablePayloadBuilder()->build($this, $this->state($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->payload();
    }

    /**
     * @param  Builder<Model>|null  $query
     * @return Builder<Model>
     */
    public function apply(?Builder $query = null, ?TableRequest $request = null): Builder
    {
        /** @var TableQueryBuilder $queryBuilder */
        $queryBuilder = clone resolve(TableQueryBuilder::class);
        $queryBuilder = $this->withQueryBuilder($queryBuilder) ?? $queryBuilder;

        return $queryBuilder->apply($query ?? $this->query(), $this, $this->state($request));
    }

    /**
     * @param  Builder<Model>|null  $query
     */
    public function paginate(?Builder $query = null, ?TableRequest $request = null): mixed
    {
        /** @var TablePaginationBuilder $paginationBuilder */
        $paginationBuilder = resolve(TablePaginationBuilder::class);

        return $paginationBuilder->paginate($query ?? $this->query(), $this, $this->state($request));
    }

    /**
     * @param  Builder<Model>|null  $query
     * @return array<string, mixed>
     */
    public function results(?Builder $query = null, ?TableRequest $request = null): array
    {
        return $this->withResolvedDefinitions(function () use ($query, $request): array {
            $state = $this->state($request);

            /** @var TablePaginationBuilder $paginationBuilder */
            $paginationBuilder = resolve(TablePaginationBuilder::class);

            /** @var TablePayloadBuilder $payloadBuilder */
            $payloadBuilder = resolve(TablePayloadBuilder::class);

            $query ??= $this->query();

            return $payloadBuilder->buildResults(
                $this,
                $state,
                $paginationBuilder->paginate($query, $this, $state),
            );
        });
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        $resource = $this->resource();

        if ($resource instanceof Builder) {
            return clone $resource;
        }

        if (is_string($resource) && is_a($resource, Model::class, true)) {
            return $resource::query();
        }

        if ($resource === null) {
            throw TableBuilderException::missingTableResource(static::class);
        }

        throw TableBuilderException::invalidTableResource(static::class, $resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->results(request: TableRequest::fromRequest(request()));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
