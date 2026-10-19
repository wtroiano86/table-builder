<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Http\Requests;

use Illuminate\Http\Request;
use Wtroiano\TableBuilder\Table;

final readonly class TableRequest
{
    /**
     * @param  array<array-key, mixed>  $input
     */
    private function __construct(
        private array $input,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self($request->query());
    }

    /**
     * @param  array<array-key, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self($input);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function for(Table $table): array
    {
        if ($table->isNamed()) {
            $state = $this->input[$table->name()] ?? [];

            return is_array($state) ? $state : [];
        }

        $state = array_intersect_key($this->input, array_flip([
            'page',
            'perPage',
            'cursor',
            'search',
            'sort',
            'direction',
            'columns',
            'sticky',
        ]));

        if ($state !== []) {
            return $state;
        }

        $legacyState = $this->input[$table->name()] ?? [];

        return is_array($legacyState) ? $legacyState : [];
    }
}
