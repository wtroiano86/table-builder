<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Exports\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Wtroiano\TableBuilder\Http\Requests\ExportRequest;
use Wtroiano\TableBuilder\Support\Callback;
use Wtroiano\TableBuilder\Table;

trait ExecutesExport
{
    public function isAuthorized(Table $table): bool
    {
        if (is_bool($this->authorize)) {
            return $this->authorize;
        }

        return (bool) Callback::invoke($this->authorize, $table, $this);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function executeUsing(Table $table, ExportRequest $request, Builder $query): mixed
    {
        return $this->using === null ? null : Callback::invoke($this->using, $table, $this, $request, $query);
    }

    public function hasCustomExporter(): bool
    {
        return $this->using !== null;
    }

    public function isLimitedToFilteredRows(): bool
    {
        return $this->limitToFilteredRows;
    }

    public function isLimitedToSelectedRows(): bool
    {
        return $this->limitToSelectedRows;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }

    public function shouldDownload(): bool
    {
        return $this->download && ! $this->queued;
    }

    public function resolvedFilename(): string
    {
        return $this->filename;
    }

    public function queueFilename(): string
    {
        return $this->queueFilename ?? $this->filename;
    }

    public function queueDisk(): ?string
    {
        return $this->queueDisk;
    }

    public function queueName(): ?string
    {
        return self::$defaultQueueName;
    }

    public function writerType(): string
    {
        return $this->type;
    }

    public function queuedJob(mixed $job): mixed
    {
        if (self::$defaultQueueName !== null && is_object($job) && is_callable([$job, 'allOnQueue'])) {
            $job = $job->allOnQueue(self::$defaultQueueName);
        }

        return $this->withQueuedJob === null ? $job : Callback::invoke($this->withQueuedJob, $job);
    }

    public function redirectResponse(): RedirectResponse
    {
        $redirect = $this->redirect;

        if ($redirect instanceof Closure) {
            $redirect = $redirect($this);
        }

        if ($redirect === 'back' || $redirect === null) {
            $response = back();
        } elseif (is_array($redirect) && isset($redirect['route'])) {
            $response = to_route(is_string($redirect['route']) ? $redirect['route'] : '', (array) ($redirect['parameters'] ?? []));
        } else {
            $response = redirect()->to(is_string($redirect) ? $redirect : '');
        }

        if ($this->dialog !== null) {
            $response->with('table_export_dialog', $this->dialog);
        }

        return $response;
    }

    /**
     * @return array<string, callable>
     */
    public function eventsDefinition(): array
    {
        return $this->events;
    }
}
