<?php

namespace Imran\DynamicConfig\Tests\Feature;

use Imran\DynamicConfig\Tests\TestCase;
use Imran\DynamicConfig\Drivers\DatabaseDriver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DatabaseDriverOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_configs', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_more_specific_key_deterministically_wins_over_a_broader_overlapping_key()
    {
        // Insert the specific row first and the broad row second - if rows
        // were read back in insertion order (no ORDER BY), the broad row
        // would be applied last and clobber the specific one. Ordering by
        // `key` ascending must make the specific key win regardless of
        // insertion order, since "services" sorts before
        // "services.stripe.display_name".
        DB::table('app_configs')->insert([
            ['key' => 'services.stripe.display_name', 'value' => 'Specific Wins', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'services', 'value' => json_encode(['stripe' => ['display_name' => 'Broad Wins']]), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $driver = new DatabaseDriver();
        $config = $driver->load(['table' => 'app_configs']);

        $this->assertEquals('Specific Wins', $config['services']['stripe']['display_name']);
    }

    public function test_rows_are_ordered_by_key_column()
    {
        DB::table('app_configs')->insert([
            ['key' => 'zeta.value', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'alpha.value', 'value' => '2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $sql = null;
        DB::listen(function ($query) use (&$sql) {
            if (str_contains($query->sql, 'app_configs')) {
                $sql = $query->sql;
            }
        });

        (new DatabaseDriver())->load(['table' => 'app_configs']);

        $this->assertStringContainsString('order by', strtolower($sql));
    }
}
