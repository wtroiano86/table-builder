<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Enums;

enum PaginationType: string
{
    case Standard = 'standard';
    case Simple = 'simple';
    case Cursor = 'cursor';
}
