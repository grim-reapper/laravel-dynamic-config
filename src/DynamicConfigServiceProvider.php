<?php

namespace Imran\DynamicConfig;

use Illuminate\Support\ServiceProvider;

class DynamicConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/dynamic-config.php', 'dynamic-config'
        );

        $this->app->singleton(ConfigManager::class, function ($app) {
            return new ConfigManager($app, $app['config']);
        });

        // Register default merge strategy if not bound
        if (!$this->app->bound(Contracts\MergeStrategy::class)) {
            $this->app->bind(Contracts\MergeStrategy::class, function ($app) {
                $strategy = $app['config']->get('dynamic-config.merge_strategy', 'deep');
                return new Merge\MergeEngine($strategy);
            });
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/dynamic-config.php' => config_path('dynamic-config.php'),
            ], 'dynamic-config-config');

            $this->commands([
                Commands\CacheCommand::class,
                Commands\ClearCommand::class,
                Commands\DebugCommand::class,
            ]);
        }

        // Boot the config manager to load dynamic configurations into Laravel's repository
        // We only do this if it's not the dynamic-config:cache command running,
        // to avoid recursive loops or using stale data while caching.
        if (!$this->isCachingCommand()) {
            $this->app->make(ConfigManager::class)->load();
        }
    }

    protected function isCachingCommand(): bool
    {
        return $this->app->runningInConsole() 
            && isset($_SERVER['argv']) 
            && in_array('dynamic-config:cache', $_SERVER['argv']);
    }
}
