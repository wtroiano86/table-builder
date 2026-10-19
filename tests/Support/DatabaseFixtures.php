<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Support;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Wtroiano\TableBuilder\Tests\Fixtures\PipelineRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityChildRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityDescendantRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\QualityParentRecord;
use Wtroiano\TableBuilder\Tests\Fixtures\UuidRecord;

final class DatabaseFixtures
{
    /** @var list<string> */
    private static array $tables = [];

    /** @param list<array<string, mixed>> $rows */
    public static function pipelineRecords(array $rows): void
    {
        self::create('pipeline_records', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('team')->nullable();
            $table->string('code')->nullable();
        });
        PipelineRecord::query()->insert($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $parents
     * @param  list<array<string, mixed>>  $children
     * @param  list<array<string, mixed>>  $descendants
     */
    public static function relations(array $parents, array $children, array $descendants = []): void
    {
        self::create('quality_parents', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('manager_id')->nullable();
        });
        self::create('quality_children', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('label')->nullable();
            $table->boolean('active');
            $table->softDeletes();
        });
        self::create('quality_descendants', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('child_id');
            $table->string('label');
        });
        QualityParentRecord::query()->insert($parents);
        QualityChildRecord::query()->insert($children);
        QualityDescendantRecord::query()->insert($descendants);
    }

    /** @param list<array<string, mixed>> $rows */
    public static function uuidRecords(array $rows): void
    {
        self::create('uuid_records', static function (Blueprint $table): void {
            $table->string('uuid')->primary();
            $table->string('name');
        });
        UuidRecord::query()->insert($rows);
    }

    public static function cleanUp(): void
    {
        foreach (array_reverse(self::$tables) as $table) {
            Schema::dropIfExists($table);
        }

        self::$tables = [];
    }

    /** @param Closure(Blueprint): void $definition */
    private static function create(string $table, Closure $definition): void
    {
        self::$tables[] = $table;
        Schema::create($table, $definition);
    }
}
