<?php

namespace Imran\DynamicConfig\Tests\Feature;

use Imran\DynamicConfig\Tests\TestCase;
use Imran\DynamicConfig\ConfigManager;

class AutoRefreshTest extends TestCase
{
    public function test_auto_refresh_rebuilds_when_a_file_source_changes_after_caching()
    {
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'json',
                'priority' => 10,
                'path' => __DIR__.'/../fixtures/app.json',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $manager->cache();
        $manager->load();

        $this->assertEquals('Json App', config('app.name'));

        // Mutate the source after the cache was built, and bump its mtime
        // forward so it's unambiguously "newer" regardless of filesystem
        // mtime resolution.
        file_put_contents(__DIR__.'/../fixtures/app.json', json_encode([
            'app' => ['name' => 'Updated Json App'],
        ]));
        touch(__DIR__.'/../fixtures/app.json', time() + 10);

        config()->set('dynamic-config.auto_refresh', true);

        $manager->load();

        $this->assertEquals('Updated Json App', config('app.name'));
    }

    public function test_without_auto_refresh_stale_cache_is_served()
    {
        config()->set('dynamic-config.sources', [
            [
                'driver' => 'json',
                'priority' => 10,
                'path' => __DIR__.'/../fixtures/app.json',
            ],
        ]);

        $manager = $this->app->make(ConfigManager::class);
        $manager->cache();
        $manager->load();

        file_put_contents(__DIR__.'/../fixtures/app.json', json_encode([
            'app' => ['name' => 'Updated Json App'],
        ]));
        touch(__DIR__.'/../fixtures/app.json', time() + 10);

        // auto_refresh left at its default (false)
        $manager->load();

        $this->assertEquals('Json App', config('app.name'));
    }
}
