<?php

namespace Imran\DynamicConfig\Tests\Feature;

use Imran\DynamicConfig\Tests\TestCase;
use Imran\DynamicConfig\ConfigManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class SecurityTest extends TestCase
{
    protected function createAppConfigsTable(): void
    {
        Schema::create('app_configs', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_protected_keys_block_database_source_from_overriding_sensitive_paths()
    {
        $this->createAppConfigsTable();

        DB::table('app_configs')->insert([
            ['key' => 'app.key', 'value' => 'base64:hijacked', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app.name', 'value' => 'DB App', 'created_at' => now(), 'updated_at' => now()],
        ]);

        config()->set('dynamic-config.protected_keys', ['app.key']);
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'database',
                'priority' => 100,
                'table' => 'app_configs',
            ],
        ]);

        $originalKey = config('app.key');

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertEquals($originalKey, config('app.key'), 'protected key must not be overridden');
        $this->assertEquals('DB App', config('app.name'), 'non-protected key should still be applied');
    }

    public function test_wildcard_style_protected_key_blocks_nested_paths()
    {
        $this->createAppConfigsTable();

        DB::table('app_configs')->insert([
            'key' => 'database.connections.mysql.host',
            'value' => 'evil.example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config()->set('dynamic-config.protected_keys', ['database.connections']);
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'database',
                'priority' => 100,
                'table' => 'app_configs',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);
    }

    public function test_fail_silently_skips_a_broken_source_without_crashing()
    {
        $badYamlPath = __DIR__.'/../fixtures/broken.yaml';
        file_put_contents($badYamlPath, "app:\n  name: [unclosed");

        config()->set('dynamic-config.fail_silently', true);
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'yaml',
                'priority' => 10,
                'path' => $badYamlPath,
            ],
            [
                'driver' => 'json',
                'priority' => 20,
                'path' => __DIR__.'/../fixtures/app.json',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertEquals('Json App', config('app.name'));

        @unlink($badYamlPath);
    }

    public function test_environments_option_skips_source_outside_matching_environment()
    {
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'json',
                'priority' => 10,
                'path' => __DIR__.'/../fixtures/app.json',
                'environments' => ['production'], // the test suite runs in 'testing'
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);
    }

    public function test_environments_option_loads_source_in_matching_environment()
    {
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'json',
                'priority' => 10,
                'path' => __DIR__.'/../fixtures/app.json',
                'environments' => ['testing'],
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals('Json App', $built['app']['name']);
    }

    public function test_app_key_is_always_protected_even_without_any_explicit_config()
    {
        $this->createAppConfigsTable();

        DB::table('app_configs')->insert([
            'key' => 'app.key', 'value' => 'base64:hijacked', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Deliberately NOT setting protected_keys at all - app.key must still
        // be safe with zero configuration.
        config()->set('dynamic-config.protected_keys', []);
        config()->set('dynamic-config.protect_sensitive_keys', false);
        config()->set('dynamic-config.sources', [
            ['driver' => 'database', 'priority' => 100, 'table' => 'app_configs'],
        ]);

        $originalKey = config('app.key');

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertEquals($originalKey, config('app.key'));
    }

    public function test_protected_key_cannot_be_bypassed_by_overwriting_its_ancestor()
    {
        $this->createAppConfigsTable();

        // A single row that sets the whole "app" root to a plain scalar
        // (e.g. a malformed/non-JSON value) instead of targeting "app.key"
        // directly. Naively stripping only exact/nested protected leaves from
        // the incoming data would miss this entirely, since "app.key" never
        // appears as a flattened key in this source's contribution.
        DB::table('app_configs')->insert([
            'key' => 'app', 'value' => 'not-an-array-scalar-value', 'created_at' => now(), 'updated_at' => now(),
        ]);

        config()->set('dynamic-config.protected_keys', ['app.key']);
        config()->set('dynamic-config.protect_sensitive_keys', false);
        config()->set('dynamic-config.sources', [
            ['driver' => 'database', 'priority' => 100, 'table' => 'app_configs'],
        ]);

        $originalKey = config('app.key');

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertEquals($originalKey, config('app.key'), 'app.key must survive an ancestor overwrite');
    }

    public function test_sensitive_looking_keys_are_protected_by_default_without_being_listed()
    {
        $this->createAppConfigsTable();

        DB::table('app_configs')->insert([
            'key' => 'services.stripe.secret', 'value' => 'sk_live_hijacked', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // protected_keys is empty - only the default sensitive-name heuristic is in play.
        config()->set('dynamic-config.protected_keys', []);
        config()->set('dynamic-config.sources', [
            ['driver' => 'database', 'priority' => 100, 'table' => 'app_configs'],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertNotEquals('sk_live_hijacked', config('services.stripe.secret'));
    }

    public function test_sensitive_key_exceptions_allow_an_explicit_deliberate_override()
    {
        $this->createAppConfigsTable();

        DB::table('app_configs')->insert([
            'key' => 'services.stripe.secret', 'value' => 'sk_live_rotated', 'created_at' => now(), 'updated_at' => now(),
        ]);

        config()->set('dynamic-config.sensitive_key_exceptions', ['services.stripe.secret']);
        config()->set('dynamic-config.sources', [
            ['driver' => 'database', 'priority' => 100, 'table' => 'app_configs'],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertEquals('sk_live_rotated', config('services.stripe.secret'));
    }

    public function test_protect_sensitive_keys_can_be_disabled_entirely()
    {
        $this->createAppConfigsTable();

        DB::table('app_configs')->insert([
            'key' => 'services.stripe.secret', 'value' => 'sk_live_rotated', 'created_at' => now(), 'updated_at' => now(),
        ]);

        config()->set('dynamic-config.protect_sensitive_keys', false);
        config()->set('dynamic-config.sources', [
            ['driver' => 'database', 'priority' => 100, 'table' => 'app_configs'],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertEquals('sk_live_rotated', config('services.stripe.secret'));
    }

    public function test_sensitive_key_protection_also_applies_to_file_based_sources_not_just_database()
    {
        // protect_sensitive_keys applies uniformly to every driver, including
        // json/yaml/php - not just database/api. A secret checked into a
        // dynamic-config JSON file is still blocked unless explicitly
        // excepted, since the merge has no way to know a file is "trusted".
        file_put_contents(__DIR__.'/../fixtures/app.json', json_encode([
            'services' => ['stripe' => ['secret' => 'sk_live_from_file']],
        ]));

        config()->set('dynamic-config.sources', [
            ['driver' => 'json', 'priority' => 10, 'path' => __DIR__.'/../fixtures/app.json'],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        $this->assertNotEquals('sk_live_from_file', config('services.stripe.secret'));
    }

    public function test_unreachable_database_connection_is_logged_and_skipped_instead_of_crashing()
    {
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'database',
                'priority' => 10,
                'table' => 'app_configs',
                'connection' => 'does-not-exist',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);
    }
}
