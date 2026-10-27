<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Columns\Concerns;

use Closure;
use InvalidArgumentException;
use Stringable;

trait ExportsColumn
{
    protected bool $exportable = true;

    protected bool $exportFormulas = false;

    protected string|Closure|null $exportLabel = null;

    protected ?Closure $exportAs = null;

    protected ?string $exportFormat = null;

    /**
     * @var array<array-key, mixed>|Closure|null
     */
    protected array|Closure|null $exportStyle = null;

    /**
     * @var array<array-key, mixed>
     */
    protected array $export = [];

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
}
