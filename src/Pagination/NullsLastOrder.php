<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Pagination;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class NullsLastOrder implements Expression
{
    public function __construct(private string $column) {}

    public function getValue(Grammar $grammar): string
    {
        return 'case when '.$grammar->wrap($this->column).' is null then 1 else 0 end';
    }
}
