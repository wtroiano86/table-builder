<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[Table(name: 'pipeline_records')]
#[WithoutTimestamps]
final class PipelineRecord extends Model
{
    use HasFactory;
    use HasFactory;
}
