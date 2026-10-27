<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonSerializable;
use Wtroiano\TableBuilder\Actions\Action;
use Wtroiano\TableBuilder\Columns\Column;
use Wtroiano\TableBuilder\Concerns\HasFactoryAlias;
use Wtroiano\TableBuilder\Exceptions\TableBuilderException;
use Wtroiano\TableBuilder\Exports\Export;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Pipelines\TableQuery\TableQueryBuilder;
use Wtroiano\TableBuilder\Support\Url;
use Wtroiano\TableBuilder\Tables\Concerns\BuildsTableResults;
use Wtroiano\TableBuilder\Tables\Concerns\InteractsWithTableState;
use Wtroiano\TableBuilder\Tables\Concerns\ResolvesTableDefinitions;
use Wtroiano\TableBuilder\Tables\TableEmptyState;

/**
 * @implements Arrayable<string, mixed>
 *
 * @phpstan-import-type ColumnPayload from Column
 *
 * @phpstan-consistent-constructor
 */
abstract class Table implements Arrayable, JsonSerializable
{
    use BuildsTableResults;
    use HasFactoryAlias;
    use InteractsWithTableState;
    use ResolvesTableDefinitions;

    /**
     * @var class-string<Model>|null
     */
    protected ?string $resource = null;

    protected ?bool $stickyHeader = false;

    protected ?string $rowSelectionKey = 'id';

    protected bool $selectable = true;

    protected bool $persistRowSelectionAcrossPages = false;

    /**
     * @var TableEmptyState|array<string, mixed>|false|null
     */
    protected TableEmptyState|array|false|null $emptyState = null;

    private ?string $tableName = null;

    private ?string $registeredTableKey = null;

    /**
     * @return list<Column>
     */
    abstract public function columns(): array;

    public static function make(): static
    {
        // @phpstan-ignore new.staticInAbstractClassStaticMethod (The factory is inherited by concrete consumer subclasses.)
        return new static();
    }

    public function name(): string
    {
        return $this->tableName ?? $this->inferTableId();
    }

    public function isNamed(): bool
    {
        return $this->tableName !== null;
    }

    public function id(): string
    {
        return $this->name();
    }

    public function registryKey(): ?string
    {
        return $this->registeredTableKey;
    }

    public function reference(): ?string
    {
        return $this->registeredTableKey === null ? static::class : 'registry:'.$this->registeredTableKey;
    }

    /** @internal Applied by TableRegistry after resolving a trusted factory. */
    public function registeredAs(string $key): static
    {
        $table = clone $this;
        $table->registeredTableKey = $key;

        return $table;
    }

    public function named(string $name): static
    {
        $table = clone $this;
        $table->tableName = Str::of($name)->trim()->toString();

        if ($table->tableName === '') {
            throw TableBuilderException::emptyTableName();
        }

        return $table;
    }

    public function as(string $name): static
    {
        return $this->named($name);
    }

    /**
     * @return Builder<Model>|string|null
     */
    public function resource(): Builder|string|null
    {
        return $this->resource;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformModel(Model $model, array $data): array
    {
        return $data;
    }

    public function rowUrl(Model $model, Url $url): string|Url|null
    {
        return null;
    }

    /**
     * @return list<Action>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return list<Export>
     */
    public function exports(): array
    {
        return [];
    }

    public function isSelectable(Model $model): bool
    {
        return true;
    }

    public function authorize(Request $request): bool
    {
        return $request->user() !== null;
    }

    /** @return array<string, mixed> */
    public function exportContext(ExportRequest $request): array
    {
        return [];
    }

    /**
     * @template TResult
     *
     * @param  array<string, mixed>  $context
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function withExportContext(array $context, Closure $callback): mixed
    {
        return $callback();
    }

    public function selectable(): bool
    {
        return $this->selectable;
    }

    /**
     * @return array<array-key, mixed>|false
     */
    public function emptyState(): TableEmptyState|array|false|null
    {
        return $this->emptyState;
    }

    public function stickyHeader(): bool
    {
        return $this->stickyHeader ?? false;
    }

    public function rowSelectionKey(): ?string
    {
        $key = $this->rowSelectionKey;

        if ($key === null) {
            return null;
        }

        $key = mb_trim($key);

        return $key === '' ? null : $key;
    }

    public function selectionPayloadKey(): ?string
    {
        return $this->rowSelectionKey() === null ? null : '_selection_key';
    }

    public function persistsRowSelectionAcrossPages(): bool
    {
        return $this->persistRowSelectionAcrossPages;
    }

    public function withQueryBuilder(TableQueryBuilder $queryBuilder): ?TableQueryBuilder
    {
        return $queryBuilder;
    }

    private function inferTableId(): string
    {
        $baseName = class_basename(static::class);

        if (str_ends_with($baseName, 'Table')) {
            $baseName = mb_substr($baseName, 0, -5);
        }

        return Str::kebab($baseName);
    }
}
