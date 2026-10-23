<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Tests\Demo;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DemoData
{
    public static function prepare(): void
    {
        if (Schema::hasTable('customers')) {
            return;
        }

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('company');
            $table->string('email');
            $table->string('plan');
            $table->boolean('active');
            $table->decimal('revenue', 12, 2);
            $table->date('joined');
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->string('company');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->date('issued');
            $table->date('due');
        });
        self::reset();
    }

    public static function reset(): void
    {
        $people = [
            ['Amara Okafor', 'Forma Studio'], ['Ben Carter', 'Northstar'],
            ['Camila Santos', 'Fieldwork'], ['Daniel Kim', 'Layers'],
            ['Elena Rossi', 'Olive & Co.'], ['Finn Murphy', 'Goodkind'],
            ['Grace Chen', 'Orbit Labs'], ['Hassan Ali', 'Sonder'],
            ['Isla Thompson', 'Daybreak'], ['James Wilson', 'Evergreen'],
            ['Keiko Tanaka', 'Paperplane'], ['Leo Martin', 'Lighthouse'],
            ['Maya Patel', 'Juniper'], ['Noah Williams', 'Common Ground'],
            ['Olivia Brown', 'Wildflower'], ['Pablo Garcia', 'Aperture'],
            ['Quinn Davis', 'Blueprint'], ['Ravi Shah', 'Meridian'],
            ['Sofia Andersson', 'Fjord'], ['Theo Laurent', 'Atelier'],
            ['Uma Desai', 'Canopy'], ['Violet Lee', 'Kindred'],
            ['Will Brooks', 'Waypoint'], ['Zara Ahmed', 'Mosaic'],
        ];

        DB::transaction(function () use ($people): void {
            Customer::query()->delete();
            Invoice::query()->delete();
            foreach ($people as $index => [$name, $company]) {
                $id = $index + 1;
                Customer::query()->create([
                    'id' => $id, 'name' => $name, 'company' => $company,
                    'email' => mb_strtolower(str_replace(' ', '.', $name)).'@example.com',
                    'plan' => ['Enterprise', 'Pro', 'Starter', 'Pro'][$index % 4],
                    'active' => $index % 5 !== 4,
                    'revenue' => [24800, 8640, 2400, 12600, 1920, 7200, 18400, 9600][$index % 8],
                    'joined' => sprintf('2026-%02d-%02d', 1 + $index % 8, 4 + $index % 23),
                ]);
                Invoice::query()->create([
                    'id' => $id, 'number' => sprintf('INV-2026-%04d', 1048 - $index),
                    'company' => $company,
                    'status' => ['Paid', 'Pending', 'Paid', 'Overdue', 'Draft', 'Paid'][$index % 6],
                    'amount' => [2400, 720, 180, 1260, 160, 600, 1840, 960][$index % 8],
                    'issued' => sprintf('2026-10-%02d', 20 - $index % 18),
                    'due' => $index % 6 === 3 ? '2026-10-21' : sprintf('2026-11-%02d', 20 - $index % 18),
                ]);
            }
        });
    }
}
