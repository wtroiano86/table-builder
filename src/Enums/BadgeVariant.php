<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Enums;

enum BadgeVariant: string
{
    case Solid = 'solid';
    case Outline = 'outline';
    case Ghost = 'ghost';
}
