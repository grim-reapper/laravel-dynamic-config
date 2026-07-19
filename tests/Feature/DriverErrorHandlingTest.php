<?php

namespace Imran\DynamicConfig\Tests\Feature;

use Imran\DynamicConfig\Tests\TestCase;
use Imran\DynamicConfig\ConfigManager;

class DriverErrorHandlingTest extends TestCase
{
    public function test_php_driver_skips_a_file_with_a_parse_error()
    {
        $badPhp = __DIR__.'/../fixtures/broken.php';
        file_put_contents($badPhp, "<?php return [ 'a' => ");

        config()->set('dynamic-config.sources', [
            [
                'driver' => 'php',
                'priority' => 10,
                'path' => $badPhp,
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);

        @unlink($badPhp);
    }

    public function test_json_driver_returns_empty_array_for_malformed_json()
    {
        $badJson = __DIR__.'/../fixtures/broken.json';
        file_put_contents($badJson, '{not valid json');

        config()->set('dynamic-config.sources', [
            [
                'driver' => 'json',
                'priority' => 10,
                'path' => $badJson,
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);

        @unlink($badJson);
    }

    public function test_unknown_driver_is_skipped_without_throwing()
    {
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'not-a-real-driver',
                'priority' => 10,
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);
    }

    public function test_source_without_a_driver_key_is_skipped()
    {
        config()->set('dynamic-config.sources', [
            ['priority' => 10, 'path' => '/nowhere'],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);
    }
}
