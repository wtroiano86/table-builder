<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Exports\Concerns;

trait SerializesExport
{
    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->keyName(),
            'label' => $this->label,
            'endpoint' => route('table-builder.exports.execute', absolute: false),
            'authorized' => true,
            'hidden' => false,
            'disabled' => false,
            'queued' => $this->queued,
            'limitToFilteredRows' => $this->limitToFilteredRows,
            'limitToSelectedRows' => $this->limitToSelectedRows,
            'asDownload' => $this->shouldDownload(),
            'meta' => $this->meta,
            'data' => $this->data,
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
