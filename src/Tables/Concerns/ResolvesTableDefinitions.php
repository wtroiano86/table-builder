<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tables\Concerns;

use Closure;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Tables\TableDefinitions;

/** @phpstan-import-type ColumnPayload from Column */
trait ResolvesTableDefinitions
{
    private ?TableDefinitions $resolvedDefinitions = null;

    public function __clone(): void
    {
        $this->resolvedDefinitions = null;
    }

    /**
     * @return list<Column>
     */
    public function columnsDefinition(): array
    {
        return $this->resolvedDefinitions->columns ?? $this->normalizeDefinitions($this->columns(), Column::class, 'columns');
    }

    /**
     * @return list<ColumnPayload>
     *
     * @internal Serialized definitions are shared only within the current render.
     */
    public function serializedColumnsDefinition(): array
    {
        return $this->resolvedDefinitions?->serializedColumns() ?? array_map(
            static fn (Column $column): array => $column->toArray(),
            $this->columnsDefinition(),
        );
    }

    /**
     * @return list<Action>
     */
    public function actionsDefinition(): array
    {
        return $this->resolvedDefinitions->actions ?? $this->normalizeDefinitions($this->actions(), Action::class, 'actions');
    }

    /**
     * @return list<Export>
     */
    public function exportsDefinition(): array
    {
        return $this->resolvedDefinitions->exports ?? $this->normalizeDefinitions($this->exports(), Export::class, 'exports');
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @internal Resolve once for a render and restore even if a callback throws.
     */
    public function withResolvedDefinitions(Closure $callback): mixed
    {
        $previous = $this->resolvedDefinitions;
        $this->resolvedDefinitions ??= new TableDefinitions(
            $this->columnsDefinition(),
            $this->actionsDefinition(),
            $this->exportsDefinition(),
        );

        try {
            return $callback();
        } finally {
            $this->resolvedDefinitions = $previous;
        }
    }

    /**
     * @template TDefinition of object
     *
     * @param  array<int, mixed>  $definitions
     * @param  class-string<TDefinition>  $expectedClass
     * @return list<TDefinition>
     */
    private function normalizeDefinitions(array $definitions, string $expectedClass, string $segment): array
    {
        foreach ($definitions as $index => $definition) {
            if (! $definition instanceof $expectedClass) {
                throw TableBuilderException::invalidTableDefinition(static::class, $segment, $expectedClass, $index);
            }
        }

        return array_values($definitions);
    }
}
