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
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ModelTable(name: 'quality_parents')]
#[Unguarded]
#[WithoutTimestamps]
final class QualityParentRecord extends Model
{
    use HasFactory;

    public function children(): HasMany
    {
        return $this->hasMany(QualityChildRecord::class, 'parent_id')->where('active', true);
    }

    public function firstChild(): HasOne
    {
        return $this->hasOne(QualityChildRecord::class, 'parent_id')->where('active', true);
    }

    public function latestChild(): HasOne
    {
        return $this->hasOne(QualityChildRecord::class, 'parent_id')->where('active', true)->orderBy('quality_children.id', 'desc');
    }

    public function preferredChild(): HasOne
    {
        return $this->hasOne(QualityChildRecord::class, 'parent_id')->where('active', true)
            ->orderByRaw('case when label = ? then 0 else 1 end', ['B'])->orderBy('quality_children.id', 'desc');
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(self::class, 'manager_id')->orderBy('quality_parents.id', 'desc');
    }

    public function lastChild(): HasOne
    {
        return $this->hasOne(QualityChildRecord::class, 'parent_id')->latestOfMany();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }
}
