<?php

namespace Imran\DynamicConfig\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Imran\DynamicConfig\DynamicConfigServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            DynamicConfigServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default config for testing
        $app['config']->set('dynamic-config.merge_strategy', 'deep');
        $app['config']->set('dynamic-config.cache_file', __DIR__.'/temp/dynamic_config.php');
        $app['config']->set('dynamic-config.sources', [
            [
                'driver'   => 'json',
                'priority' => 10,
                'path'     => __DIR__.'/fixtures/app.json',
            ],
            [
                'driver'   => 'yaml',
                'priority' => 20,
                'path'     => __DIR__.'/fixtures/features.yaml',
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!is_dir(__DIR__.'/temp')) {
            mkdir(__DIR__.'/temp');
        }
        if (!is_dir(__DIR__.'/fixtures')) {
            mkdir(__DIR__.'/fixtures');
        }

        // Create fixtures
        file_put_contents(__DIR__.'/fixtures/app.json', json_encode([
            'app' => [
                'name' => 'Json App',
                'env' => 'production'
            ]
        ]));

        file_put_contents(__DIR__.'/fixtures/features.yaml', "app:\n  name: Yaml App\nfeatures:\n  dark_mode: true");
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__.'/temp/dynamic_config.php');
        @unlink(__DIR__.'/fixtures/app.json');
        @unlink(__DIR__.'/fixtures/features.yaml');
        @rmdir(__DIR__.'/temp');
        @rmdir(__DIR__.'/fixtures');
        
        parent::tearDown();
    }
}
