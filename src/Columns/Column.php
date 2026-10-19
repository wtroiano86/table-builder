<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionNamedType;
use Stringable;
use Wtroiano\TableBuilder\Concerns\HasFactoryAlias;
use Wtroiano\TableBuilder\Enums\ColumnAlignment;
use Wtroiano\TableBuilder\Support\Image;
use Wtroiano\TableBuilder\Support\Url;

/**
 * @phpstan-consistent-constructor
 *
 * @phpstan-type ColumnPayload array{key: string, name: string, type: string, label: string, visible: bool, sortable: bool, searchable: bool, toggleable: bool, sticky: bool, alignment: ?string, width: string|int|null, minWidth: string|int|null, maxWidth: string|int|null, labelClass: ?string, cellClass: ?string, defaultValue: mixed, export: array<array-key, mixed>, meta: array<array-key, mixed>, tooltip?: ?string}
 * @phpstan-type ColumnOptions array{key: ?string, label: string|callable|null, mapAs: ?callable, visible: bool, sortable: bool, searchable: bool, alignment: string|ColumnAlignment|null, meta: array<array-key, mixed>, toggleable: bool, sticky: bool, sortUsing: ?callable, searchUsing: ?callable, labelClass: ?string, cellClass: ?string, exportAs: false|callable|null, exportLabel: string|callable|null, exportFormat: ?string, exportStyle: array<array-key, mixed>|callable|null}
 */
abstract class Column
{
    use HasFactoryAlias;

    protected ?string $key = null;

    protected string|Closure|null $label = null;

    protected bool $visible = true;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected ?Closure $searchUsing = null;

    protected ?Closure $sortUsing = null;

    protected bool $toggleable = true;

    protected bool $sticky = false;

    protected string|int|null $width = null;

    protected string|int|null $minWidth = null;

    protected string|int|null $maxWidth = null;

    protected string|ColumnAlignment|null $alignment = null;

    protected ?string $labelClass = null;

    protected ?string $cellClass = null;

    protected ?string $tooltip = null;

    protected mixed $defaultValue = null;

    protected ?Closure $mapAs = null;

    protected bool $exportable = true;

    protected bool $exportFormulas = false;

    protected string|Closure|null $exportLabel = null;

    protected ?Closure $exportAs = null;

    protected ?string $exportFormat = null;

    /**
     * @var array<array-key, mixed>|Closure|null
     */
    protected array|Closure|null $exportStyle = null;

    protected string|Url|Closure|null $url = null;

    protected string|Closure|null $image = null;

    protected ?Closure $configureImage = null;

    /**
     * @var array<array-key, mixed>
     */
    protected array $export = [];

    /**
     * @var array<array-key, mixed>
     */
    protected array $meta = [];

    protected function __construct(protected string $name) {}

    /**
     * @param  array<array-key, mixed>|callable|null  $exportStyle
     * @param  array<array-key, mixed>  $meta
     */
    public static function make(
        string $name,
        string|callable|null $label = null,
        bool $sortable = false,
        bool $toggleable = true,
        bool $searchable = false,
        string|ColumnAlignment|null $alignment = null,
        ?callable $mapAs = null,
        bool $visible = true,
        ?callable $sortUsing = null,
        array $meta = [],
        bool $sticky = false,
        ?callable $searchUsing = null,
        ?string $key = null,
        ?string $labelClass = null,
        ?string $cellClass = null,
        false|callable|null $exportAs = null,
        string|callable|null $exportLabel = null,
        ?string $exportFormat = null,
        array|callable|null $exportStyle = null,
    ): static {
        $options = get_defined_vars();
        unset($options['name']);

        return static::fromOptions($name, $options);
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

    public function toggleable(bool $toggleable = true): static
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    public function isToggleable(): bool
    {
        return $this->toggleable;
    }

    public function sticky(bool $sticky = true): static
    {
        $this->sticky = $sticky;

        return $this;
    }

    public function alignment(string|ColumnAlignment|null $alignment): static
    {
        if ($alignment === null) {
            return $this;
        }

        $this->alignment = is_string($alignment)
            ? ColumnAlignment::tryFrom($alignment) ?? $alignment
            : $alignment;

        return $this;
    }

    public function width(string|int|null $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function minWidth(string|int|null $minWidth): static
    {
        $this->minWidth = $minWidth;

        return $this;
    }

    public function maxWidth(string|int|null $maxWidth): static
    {
        $this->maxWidth = $maxWidth;

        return $this;
    }

    public function labelClass(?string $labelClass): static
    {
        $this->labelClass = $labelClass;

        return $this;
    }

    public function cellClass(?string $cellClass): static
    {
        $this->cellClass = $cellClass;

        return $this;
    }

    public function tooltip(?string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function defaultValue(mixed $defaultValue): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function mapAs(?callable $mapAs): static
    {
        if ($mapAs === null) {
            return $this;
        }

        $this->mapAs = Closure::fromCallable($mapAs);

        return $this;
    }

    public function url(string|Url|callable|null $url): static
    {
        if ($url === null) {
            return $this;
        }

        $this->url = is_callable($url) && ! is_string($url)
            ? Closure::fromCallable($url)
            : $url;

        return $this;
    }

    /**
     * @return string|array<array-key, mixed>|null
     */
    public function resolveUrl(mixed $record): string|array|null
    {
        if ($this->url === null) {
            return null;
        }

        $url = $this->url instanceof Closure
            ? ($this->url)($record, new Url())
            : $this->url;

        if ($url instanceof Url) {
            return $url->toArray();
        }

        return is_string($url) || is_array($url) ? $url : null;
    }

    public function image(string|callable|null $image, ?callable $configure = null): static
    {
        if ($image !== null) {
            $this->image = is_callable($image) && ! is_string($image)
                ? Closure::fromCallable($image)
                : $image;
        }

        if ($configure !== null) {
            $this->configureImage = Closure::fromCallable($configure);
        }

        return $this;
    }

    /**
     * @return array{
     *     url: ?string,
     *     urls: list<string>,
     *     icon: ?string,
     *     size: string,
     *     width: ?int,
     *     height: ?int,
     *     rounded: bool,
     *     position: string,
     *     class: string,
     *     alt: string,
     *     title: string,
     *     limit: ?int
     * }|null
     */
    public function resolveImage(mixed $record): ?array
    {
        if ($this->image === null && ! $this->configureImage instanceof Closure) {
            return null;
        }

        $image = new Image();

        if (is_string($this->image)) {
            $value = data_get($record, $this->image);

            if (is_string($value) || is_array($value)) {
                $image->url(is_array($value) ? array_values(array_filter($value, is_string(...))) : $value);
            }
        }

        if ($this->image instanceof Closure) {
            $resolved = $this->invokeImageCallback($this->image, $record, $image);

            if ($resolved instanceof Image) {
                $image = $resolved;
            } elseif (is_string($resolved) || is_array($resolved)) {
                $image->url(is_array($resolved) ? array_values(array_filter($resolved, is_string(...))) : $resolved);
            }
        }

        if ($this->configureImage instanceof Closure) {
            $configured = $this->invokeImageCallback($this->configureImage, $record, $image);

            if ($configured instanceof Image) {
                $image = $configured;
            }
        }

        return $image->toArray();
    }

    /**
     * @param  array<array-key, mixed>  $configuration
     */
    public function export(array $configuration): static
    {
        $enabled = $configuration['enabled'] ?? true;
        $format = $configuration['format'] ?? null;
        $style = $configuration['style'] ?? null;

        throw_if(! is_bool($enabled) || ($format !== null && ! is_string($format)) || ($style !== null && ! is_array($style) && ! is_callable($style)), InvalidArgumentException::class, 'Export options require a boolean enabled flag, a string or null format, and an array, callable, or null style.');

        unset($configuration['enabled'], $configuration['format'], $configuration['style'], $configuration['custom']);
        $this->export = $configuration;

        return $this->dontExport(! $enabled)->exportFormat($format)->exportStyle($style);
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

    public function exportAs(false|callable|null $exportAs = null): static
    {
        if ($exportAs === false) {
            return $this->dontExport();
        }

        if ($exportAs === null) {
            return $this;
        }

        $this->exportAs = Closure::fromCallable($exportAs);

        return $this;
    }

    public function exportFormat(?string $format): static
    {
        $this->exportFormat = $format;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>|callable|null  $style
     */
    public function exportStyle(array|callable|null $style): static
    {
        $this->exportStyle = is_callable($style)
            ? Closure::fromCallable($style)
            : $style;

        return $this;
    }

    public function exportLabel(string|callable|null $label): static
    {
        if ($label === null) {
            return $this;
        }

        $this->exportLabel = is_string($label)
            ? $label
            : Closure::fromCallable($label);

        return $this;
    }

    public function dontExport(bool $condition = true): static
    {
        $this->exportable = ! $condition;

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function resolvedExportLabel(): string
    {
        if ($this->exportLabel instanceof Closure) {
            $label = ($this->exportLabel)();

            return is_scalar($label) || $label instanceof Stringable ? (string) $label : '';
        }

        return $this->exportLabel ?? $this->resolvedLabel();
    }

    public function resolveExportValue(mixed $record): mixed
    {
        $value = $this->resolveValue($record);

        if (! $this->exportAs instanceof Closure) {
            return $value;
        }

        return ($this->exportAs)($value, $record);
    }

    public function exportFormulas(bool $enabled = true): static
    {
        $this->exportFormulas = $enabled;

        return $this;
    }

    public function allowsExportFormulas(): bool
    {
        return $this->exportFormulas;
    }

    public function resolvedExportFormat(): ?string
    {
        return $this->exportFormat;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function resolvedExportStyle(mixed $sheet = null): ?array
    {
        if ($this->exportStyle instanceof Closure) {
            $style = ($this->exportStyle)($sheet, $this);

            return is_array($style) ? $style : null;
        }

        return $this->exportStyle;
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

    /** @param ColumnOptions $options */
    protected static function fromOptions(string $name, array $options): static
    {
        // Consumers inherit this factory; consistent constructors preserve extension safety.
        // @phpstan-ignore new.staticInAbstractClassStaticMethod
        return new static($name)
            ->key($options['key'])
            ->label($options['label'])
            ->mapAs($options['mapAs'])
            ->visible($options['visible'])
            ->sortable($options['sortable'])
            ->searchable($options['searchable'])
            ->alignment($options['alignment'])
            ->meta($options['meta'])
            ->toggleable($options['toggleable'])
            ->sticky($options['sticky'])
            ->sortUsing($options['sortUsing'])
            ->searchUsing($options['searchUsing'])
            ->labelClass($options['labelClass'])
            ->cellClass($options['cellClass'])
            ->exportAs($options['exportAs'])
            ->exportLabel($options['exportLabel'])
            ->exportFormat($options['exportFormat'])
            ->exportStyle($options['exportStyle']);
    }

    /** @return array<array-key, mixed> */
    private function exportConfiguration(): array
    {
        $configuration = array_replace($this->export, [
            'enabled' => $this->exportable,
            'custom' => $this->exportAs instanceof Closure,
        ]);

        if ($this->exportFormat !== null) {
            $configuration['format'] = $this->exportFormat;
        }

        if (is_array($this->exportStyle)) {
            $configuration['style'] = $this->exportStyle;
        }

        return $configuration;
    }

    private function invokeImageCallback(Closure $callback, mixed $record, Image $image): mixed
    {
        $reflection = new ReflectionFunction($callback);
        $parameters = $reflection->getParameters();

        if ($parameters === []) {
            return $callback();
        }

        $firstType = $parameters[0]->getType();

        if ($firstType instanceof ReflectionNamedType && ! $firstType->isBuiltin() && $firstType->getName() === Image::class) {
            return count($parameters) === 1
                ? $callback($image)
                : $callback($image, $record);
        }

        if (count($parameters) === 1) {
            return $callback($record);
        }

        return $callback($record, $image);
    }
}
