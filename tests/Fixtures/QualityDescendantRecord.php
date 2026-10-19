<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Table as ModelTable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ModelTable(name: 'quality_descendants')]
#[Unguarded]
#[WithoutTimestamps]
final class QualityDescendantRecord extends Model
{
    use HasFactory;
}
