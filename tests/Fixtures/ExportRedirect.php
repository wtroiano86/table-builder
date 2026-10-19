<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Wtroiano\TableBuilder\Exports\Export;

final class ExportRedirect
{
    public const array CALLBACK = [self::class, 'complete'];

    public static function complete(Export $export): string
    {
        return '/completed/'.$export->keyName();
    }
}
