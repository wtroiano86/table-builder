<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Demo;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[Table(name: 'customers')]
#[WithoutTimestamps]
final class Customer extends Model
{
    use HasFactory;
}
