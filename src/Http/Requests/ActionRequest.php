<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Http\Requests;

use Illuminate\Http\Request;

final readonly class ActionRequest
{
    private function __construct(public string $table, public string $action) {}

    public static function fromRequest(Request $request): self
    {
        $request->validate([
            'table' => ['required', 'string'],
            'action' => ['sometimes', 'nullable', 'string'],
        ]);

        return new self($request->string('table')->toString(), $request->string('action')->toString());
    }
}
