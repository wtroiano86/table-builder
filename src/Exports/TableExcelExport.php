<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Http\Requests\TableRequest;
use Wtroiano\TableBuilder\Pipelines\ExportQuery\ExportQueryBuilder;
use Wtroiano\TableBuilder\Table;
use Wtroiano\TableBuilder\Tables\TableResolver;

/** @implements WithMapping<mixed> */
final class TableExcelExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles
{
    /** @var list<Column>|null */
    private ?array $columns = null;

    private ?Table $table = null;

    private ?Export $export = null;

    /** @var Builder<Model>|null */
    private ?Builder $preparedQuery = null;

    /**
     * @param  string  $tableClass  Table class name or registry reference.
     * @param  array<array-key, mixed>  $tableRequestInput
     * @param  list<int|string>  $selectedKeys
     * @param  array<string, mixed>  $exportContext
     */
    public function __construct(
        private string $tableClass,
        private string $exportKey,
        private array $tableRequestInput = [],
        private array $selectedKeys = [],
        private ?string $tableName = null,
        private array $exportContext = [],
    ) {}

    /**
     * @return array{tableClass: string, exportKey: string, tableRequestInput: array<array-key, mixed>, selectedKeys: list<int|string>, tableName: ?string, exportContext: array<string, mixed>}
     */
    public function __serialize(): array
    {
        return [
            'tableClass' => $this->tableClass,
            'exportKey' => $this->exportKey,
            'tableRequestInput' => $this->tableRequestInput,
            'selectedKeys' => $this->selectedKeys,
            'tableName' => $this->tableName,
            'exportContext' => $this->exportContext,
        ];
    }

    /**
     * @param  array{tableClass: string, exportKey: string, tableRequestInput?: array<string, mixed>, selectedKeys?: list<int|string>, tableName?: ?string, exportContext?: array<string, mixed>}  $data
     */
    public function __unserialize(array $data): void
    {
        $this->tableClass = $data['tableClass'];
        $this->exportKey = $data['exportKey'];
        $this->tableRequestInput = $data['tableRequestInput'] ?? [];
        $this->selectedKeys = $data['selectedKeys'] ?? [];
        $this->tableName = $data['tableName'] ?? null;
        $this->exportContext = $data['exportContext'] ?? [];
        $this->columns = null;
        $this->table = null;
        $this->export = null;
        $this->preparedQuery = null;
    }

    /** @param Builder<Model>|null $preparedQuery */
    public static function fromRequest(Table $table, Export $export, ExportRequest $request, ?Builder $preparedQuery = null): self
    {
        $instance = new self(
            $table->reference() ?? throw new RuntimeException('Register fluent tables with TableRegistry before exporting.'),
            $export->keyName(),
            $request->tableRequestInput($table),
            $request->selectedKeys(),
            $table->isNamed() ? $table->name() : null,
            $table->exportContext($request),
        );
        $instance->table = $table;
        $instance->export = $export;
        $instance->preparedQuery = $preparedQuery instanceof Builder ? clone $preparedQuery : null;

        return $instance;
    }

    public function query(): Builder
    {
        return $this->withContext(function (): Builder {
            if ($this->preparedQuery instanceof Builder) {
                return (clone $this->preparedQuery)->applyScopes()->withoutGlobalScopes();
            }

            $table = $this->table();

            /** @var ExportQueryBuilder $queryBuilder */
            $queryBuilder = resolve(ExportQueryBuilder::class);

            $this->preparedQuery = $queryBuilder->apply(
                $table,
                $this->export($table),
                TableRequest::fromArray($this->tableRequestInput),
                $this->selectedKeys,
            );

            return (clone $this->preparedQuery)->applyScopes()->withoutGlobalScopes();
        });
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->withContext(fn (): array => array_map(
            static fn (Column $column): string => $column->resolvedExportLabel(),
            $this->columns(),
        ));
    }

    /**
     * @return list<mixed>
     */
    public function map(mixed $row): array
    {
        return $this->withContext(fn (): array => array_map(
            static fn (Column $column): mixed => $column->resolveExportValue($row),
            $this->columns(),
        ));
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return $this->withContext(function (): array {
            $formats = [];

            foreach ($this->columns() as $index => $column) {
                $format = $column->resolvedExportFormat();

                if ($format === null) {
                    continue;
                }

                $formats[Coordinate::stringFromColumnIndex($index + 1)] = $format;
            }

            return $formats;
        });
    }

    /**
     * @return array<int|string, array<array-key, mixed>>|null
     */
    public function styles(Worksheet $sheet): ?array
    {
        return $this->withContext(function () use ($sheet): ?array {
            $styles = [];

            foreach ($this->columns() as $index => $column) {
                $style = $column->resolvedExportStyle($sheet);

                if ($style === null || $style === []) {
                    continue;
                }

                $styles[Coordinate::stringFromColumnIndex($index + 1)] = $style;
            }

            return $styles === [] ? null : $styles;
        });
    }

    public function registerEvents(): array
    {
        return $this->withContext(fn (): array => array_map(
            fn (callable $listener): Closure => fn (mixed ...$arguments): mixed => $this->withContext(
                static fn (): mixed => $listener(...$arguments),
            ),
            $this->export($this->table())->eventsDefinition(),
        ));
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        return $this->withContext(function () use ($cell, $value): bool {
            $column = $this->columns()[Coordinate::columnIndexFromString($cell->getColumn()) - 1] ?? null;

            if (is_string($value) && ($cell->getRow() === 1 || ! $column?->allowsExportFormulas())) {
                if ($this->export($this->table())->writerType() === Excel::CSV && preg_match('/^[=+\-@\t\r\n]/', $value) === 1) {
                    $value = "'".$value;
                }

                $cell->setValueExplicit($value, DataType::TYPE_STRING);

                return true;
            }

            return new DefaultValueBinder()->bindValue($cell, $value);
        });
    }

    /** @return list<ExportContextMiddleware> */
    public function middleware(): array
    {
        return [new ExportContextMiddleware($this)];
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @internal Used by the queue middleware and Excel callbacks.
     */
    public function withContext(Closure $callback): mixed
    {
        return $this->table()->withExportContext($this->exportContext, $callback);
    }

    /**
     * @return list<Column>
     */
    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        return $this->columns = array_values(array_filter(
            $this->table()->columnsDefinition(),
            static fn (Column $column): bool => $column->isExportable(),
        ));
    }

    private function table(): Table
    {
        if ($this->table instanceof Table) {
            return $this->table;
        }

        $table = resolve(TableResolver::class)->resolve($this->tableClass);

        return $this->table = $this->tableName === null ? $table : $table->named($this->tableName);
    }

    private function export(Table $table): Export
    {
        if ($this->export instanceof Export) {
            return $this->export;
        }

        foreach ($table->exportsDefinition() as $export) {
            if ($export->keyName() === $this->exportKey) {
                return $this->export = $export;
            }
        }

        throw new RuntimeException("Export [{$this->exportKey}] could not be resolved for [{$this->tableClass}].");
    }
}
