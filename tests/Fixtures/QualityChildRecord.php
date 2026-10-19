<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Table as ModelTable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ModelTable(name: 'quality_children')]
#[Unguarded]
#[WithoutTimestamps]
final class QualityChildRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(QualityParentRecord::class, 'parent_id');
    }

    public function descendants(): HasMany
    {
        return $this->hasMany(QualityDescendantRecord::class, 'child_id');
    }
}
