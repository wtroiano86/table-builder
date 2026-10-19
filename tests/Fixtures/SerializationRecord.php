<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

#[Appends(['unrelated'])]
#[Table(name: 'pipeline_records')]
final class SerializationRecord extends Model
{
    use HasFactory;

    protected function unrelated(): Attribute
    {
        return Attribute::make(get: function (): never {
            throw new RuntimeException('An unrelated model accessor must not run.');
        });
    }
}
