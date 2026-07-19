<?php

namespace Imran\DynamicConfig;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArgvInput;

class DynamicConfigServiceProvider extends ServiceProvider
{
    protected const OWN_COMMANDS = [
        'dynamic-config:cache',
        'dynamic-config:clear',
        'dynamic-config:debug',
    ];

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

            $this->publishes([
                __DIR__ . '/../database/migrations/2024_01_01_000000_create_app_configs_table.php'
                    => database_path('migrations/' . date('Y_m_d_His') . '_create_app_configs_table.php'),
            ], 'dynamic-config-migrations');

            $this->commands([
                Commands\CacheCommand::class,
                Commands\ClearCommand::class,
                Commands\DebugCommand::class,
            ]);
        }

        // Boot the config manager to load dynamic configurations into Laravel's repository.
        // Skipped for the package's own artisan commands, which each manage the
        // config lifecycle themselves and would otherwise redo the work (or, for
        // `:cache`, risk loading stale data right before it gets overwritten).
        if (!$this->isOwnCommand()) {
            try {
                $this->app->make(ConfigManager::class)->load();
            } catch (\Throwable $e) {
                // Never let a broken dynamic-config source take the whole
                // application down. Drivers already fail soft internally; this
                // is the last-resort net for anything unexpected (e.g. a bug
                // in a custom MergeStrategy or driver registered via extend()).
                Log::error('[dynamic-config] Failed to load dynamic configuration: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Determine whether the currently running artisan command is one of this
     * package's own commands. Uses the actual first CLI argument (the command
     * name) rather than a substring search over the whole argv list, so it
     * won't false-positive on things like `php artisan list` or
     * `php artisan help dynamic-config:cache`.
     */
    protected function isOwnCommand(): bool
    {
        if (!$this->app->runningInConsole() || !isset($_SERVER['argv'])) {
            return false;
        }

        $input = new ArgvInput($_SERVER['argv']);

        return in_array($input->getFirstArgument(), self::OWN_COMMANDS, true);
    }
}
