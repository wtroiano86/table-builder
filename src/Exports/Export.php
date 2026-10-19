<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Exports;

use Closure;
use Illuminate\Support\Traits\Conditionable;
use JsonSerializable;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Wtroiano\TableBuilder\Concerns\HasFactoryAlias;
use Wtroiano\TableBuilder\Exports\Concerns\ConfiguresExport;
use Wtroiano\TableBuilder\Exports\Concerns\ExecutesExport;
use Wtroiano\TableBuilder\Exports\Concerns\SerializesExport;

final class Export implements JsonSerializable
{
    use Conditionable;
    use ConfiguresExport;
    use ExecutesExport;
    use HasFactoryAlias;
    use SerializesExport;

    private static bool $defaultLimitToFilteredRows = false;

    private static bool $defaultLimitToSelectedRows = false;

    private static ?string $defaultQueueName = null;

    private static ?string $defaultQueueDisk = null;

    private string $label = 'Export';

    private ?string $key = null;

    private string $filename = 'export.xlsx';

    private string $type = ExcelWriter::XLSX;

    private bool|Closure $authorize = true;

    private bool $limitToFilteredRows;

    private bool $limitToSelectedRows;

    private bool $queued = false;

    private ?string $queueFilename = null;

    private ?string $queueDisk;

    private ?Closure $withQueuedJob = null;

    private ?Closure $using = null;

    private bool $download = true;

    /**
     * @var string|array<array-key, mixed>|Closure|null
     */
    private string|array|Closure|null $redirect = null;

    /**
     * @var array<array-key, mixed>|null
     */
    private ?array $dialog = null;

    /**
     * @var array<string, callable>
     */
    private array $events = [];

    /**
     * @var array<array-key, mixed>
     */
    private array $meta = [];

    /**
     * @var array<array-key, mixed>
     */
    private array $data = [];

    public function __construct()
    {
        $this->limitToFilteredRows = self::$defaultLimitToFilteredRows;
        $this->limitToSelectedRows = self::$defaultLimitToSelectedRows;
        $this->queueDisk = self::$defaultQueueDisk;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, callable>  $events
     * @param  array<array-key, mixed>  $meta
     */
    public static function make(
        ?string $label = null,
        ?string $filename = null,
        ?string $type = null,
        bool|callable|null $authorize = null,
        bool $limitToFilteredRows = false,
        bool $limitToSelectedRows = false,
        bool $queued = false,
        ?callable $using = null,
        array $meta = [],
        array $data = [],
        array $events = [],
    ): self {
        $export = new self();

        $export->label($label);
        $export->filename($filename);
        $export->type($type);

        if ($authorize !== null) {
            $export->authorize($authorize);
        }

        if ($limitToFilteredRows) {
            $export->limitToFilteredRows();
        }

        if ($limitToSelectedRows) {
            $export->limitToSelectedRows();
        }

        if ($queued) {
            $export->queue();
        }

        $export->using($using);
        $export->meta($meta);
        $export->dataAttributes($data);
        $export->events($events);

        return $export;
    }

    public static function defaultLimitToFilteredRows(bool $enabled = true): void
    {
        self::$defaultLimitToFilteredRows = $enabled;
    }

    public static function defaultLimitToSelectedRows(bool $enabled = true): void
    {
        self::$defaultLimitToSelectedRows = $enabled;
    }

    public static function defaultQueueName(?string $queue = null): void
    {
        self::$defaultQueueName = $queue;
    }

    public static function defaultQueueDisk(?string $disk = null): void
    {
        self::$defaultQueueDisk = $disk;
    }
}
