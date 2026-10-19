<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

final class TransportStyle
{
    public const array CALLBACK = [self::class, 'bold'];

    public static function bold(): array
    {
        return ['font' => ['bold' => true]];
    }
}
