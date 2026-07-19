<?php

namespace Imran\DynamicConfig\Tests\Feature;

use Imran\DynamicConfig\Tests\TestCase;
use Imran\DynamicConfig\ConfigManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ApiDriverTest extends TestCase
{
    public function test_api_driver_loads_json_response()
    {
        Http::fake([
            'https://config.example.test/*' => Http::response([
                'feature' => ['new_ui' => true],
            ], 200),
        ]);

        config()->set('dynamic-config.sources', [
            [
                'driver' => 'api',
                'priority' => 10,
                'url' => 'https://config.example.test/config',
                'token' => 'test-token',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertTrue($built['feature']['new_ui']);
    }

    public function test_api_driver_fails_soft_on_non_successful_response()
    {
        Http::fake([
            'https://config.example.test/*' => Http::response('Server Error', 500),
        ]);

        config()->set('dynamic-config.sources', [
            [
                'driver' => 'api',
                'priority' => 10,
                'url' => 'https://config.example.test/config',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);
    }

    public function test_api_driver_fails_soft_on_connection_exception()
    {
        Http::fake(function () {
            throw new ConnectionException('Could not connect');
        });

        config()->set('dynamic-config.sources', [
            [
                'driver' => 'api',
                'priority' => 10,
                'url' => 'https://config.example.test/config',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $built = $manager->buildConfig();

        $this->assertEquals([], $built);
    }
}
