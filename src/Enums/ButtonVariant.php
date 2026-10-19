<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Enums;

enum ButtonVariant: string
{
    case Solid = 'solid';
    case Outline = 'outline';
    case Ghost = 'ghost';
    case Link = 'link';
}
