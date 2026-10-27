<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

use ArgumentCountError;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stringable;
use Wtroiano\TableBuilder\Columns\Concerns\ConfiguresColumnPresentation;
use Wtroiano\TableBuilder\Columns\Concerns\ExportsColumn;
use Wtroiano\TableBuilder\Columns\Concerns\ResolvesColumnMedia;
use Wtroiano\TableBuilder\Concerns\HasFactoryAlias;
use Wtroiano\TableBuilder\Enums\ColumnAlignment;

/**
 * @phpstan-consistent-constructor
 *
 * @phpstan-type ColumnPayload array{key: string, name: string, type: string, label: string, visible: bool, sortable: bool, searchable: bool, toggleable: bool, sticky: bool, alignment: ?string, width: string|int|null, minWidth: string|int|null, maxWidth: string|int|null, labelClass: ?string, cellClass: ?string, defaultValue: mixed, export: array<array-key, mixed>, meta: array<array-key, mixed>, tooltip?: ?string}
 */
abstract class Column
{
    use ConfiguresColumnPresentation;
    use ExportsColumn;
    use HasFactoryAlias;
    use ResolvesColumnMedia;

    protected ?string $key = null;

    protected string|Closure|null $label = null;

    protected bool $visible = true;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected ?Closure $searchUsing = null;

    protected ?Closure $sortUsing = null;

    protected ?Closure $mapAs = null;

    /**
     * @var array<array-key, mixed>
     */
    protected array $meta = [];

    protected function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        throw_if(func_num_args() > 1, ArgumentCountError::class, 'Use fluent methods for additional factory configuration.');

        // @phpstan-ignore new.staticInAbstractClassStaticMethod (Consumers inherit this factory with a consistent constructor.)
        return new static($name);
    }

    public function type(): string
    {
        return Str::of(class_basename(static::class))
            ->beforeLast('Column')
            ->kebab()
            ->toString();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function key(?string $key): static
    {
        if ($key === null) {
            return $this;
        }

        $this->key = $key;

        return $this;
    }

    public function label(string|callable|null $label): static
    {
        if ($label === null) {
            return $this;
        }

        $this->label = is_string($label)
            ? $label
            : Closure::fromCallable($label);

        return $this;
    }

    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function searchUsing(?callable $callback): static
    {
        if ($callback === null) {
            return $this;
        }

        $this->searchUsing = Closure::fromCallable($callback);

        return $this;
    }

    public function searchCallback(): ?Closure
    {
        return $this->searchUsing;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function sortUsing(?callable $callback): static
    {
        if ($callback === null) {
            return $this;
        }

        $this->sortUsing = Closure::fromCallable($callback);

        return $this;
    }

    public function sortCallback(): ?Closure
    {
        return $this->sortUsing;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function mapAs(?callable $mapAs): static
    {
        if ($mapAs === null) {
            return $this;
        }

        $this->mapAs = Closure::fromCallable($mapAs);

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = array_replace($this->meta, $meta);

        return $this;
    }

    public function resolvedKey(): string
    {
        return $this->key ?? $this->name;
    }

    public function resolvedLabel(): string
    {
        if ($this->label instanceof Closure) {
            $label = ($this->label)();

            return is_scalar($label) || $label instanceof Stringable ? (string) $label : '';
        }

        return $this->label ?? Str::headline(str_replace(['.', '_'], ' ', $this->name));
    }

    public function resolveValue(mixed $record): mixed
    {
        $path = $this->name;
        if ($record instanceof Model && str_contains($path, '.')) {
            $separator = (int) mb_strrpos($path, '.');
            $source = mb_substr($path, 0, $separator);
            $attribute = mb_substr($path, $separator + 1);

            // SQL table names and aliases are absent from hydrated attributes.
            if ($source === $record->getTable() || (! str_contains($source, '.') && ! $record->hasAttribute($source) && ! $record->isRelation($source) && ! $record->relationLoaded($source) && $record->hasAttribute($attribute))) {
                $path = $attribute;
            }
        }

        $value = data_get($record, $path);

        if (! $this->mapAs instanceof Closure) {
            return $value;
        }

        return ($this->mapAs)($value, $record);
    }

    /**
     * @return ColumnPayload
     */
    public function toArray(): array
    {
        $column = [
            'key' => $this->resolvedKey(),
            'name' => $this->name,
            'type' => $this->type(),
            'label' => $this->resolvedLabel(),
            'visible' => $this->visible,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'toggleable' => $this->toggleable,
            'sticky' => $this->sticky,
            'alignment' => $this->alignment instanceof ColumnAlignment
                ? $this->alignment->value
                : $this->alignment,
            'width' => $this->width,
            'minWidth' => $this->minWidth,
            'maxWidth' => $this->maxWidth,
            'labelClass' => $this->labelClass,
            'cellClass' => $this->cellClass,
            'defaultValue' => $this->defaultValue,
            'export' => $this->exportConfiguration(),
            'meta' => $this->meta,
        ];

        if ($this->tooltip !== null) {
            $column['tooltip'] = $this->tooltip;
        }

        return $column;
    }
}
