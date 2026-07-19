<?php

namespace Imran\DynamicConfig\Tests\Feature;

use Imran\DynamicConfig\Tests\TestCase;
use Imran\DynamicConfig\ConfigManager;
use Imran\DynamicConfig\Cache\ConfigCache;

class BasicTest extends TestCase
{
    public function test_it_loads_and_merges_configs()
    {
        /** @var ConfigManager $manager */
        $manager = $this->app->make(ConfigManager::class);
        $manager->load();

        // Check if values were injected into laravel config
        $this->assertEquals('Yaml App', config('app.name')); // Yaml has higher priority (20) than Json (10)
        $this->assertEquals('production', config('app.env')); // Preserved from Json
        $this->assertTrue(config('features.dark_mode')); // From Yaml
    }

    public function test_cache_command_creates_file()
    {
        $this->artisan('dynamic-config:cache')
             ->assertExitCode(0);

        $this->assertFileExists(__DIR__.'/../temp/dynamic_config.php');

        $cache = new ConfigCache(__DIR__.'/../temp/dynamic_config.php');
        $cached = $cache->load();
        $this->assertEquals('Yaml App', $cached['app']['name']);
    }

    public function test_clear_command_removes_file()
    {
        $this->artisan('dynamic-config:cache');
        $this->assertFileExists(__DIR__.'/../temp/dynamic_config.php');

        $this->artisan('dynamic-config:clear')
             ->assertExitCode(0);

        $this->assertFileDoesNotExist(__DIR__.'/../temp/dynamic_config.php');
    }
}
