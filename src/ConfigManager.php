<?php

namespace Imran\DynamicConfig;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Imran\DynamicConfig\Contracts\MergeStrategy;
use Imran\DynamicConfig\Merge\MergeEngine;
use Imran\DynamicConfig\Cache\ConfigCache;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;

class ConfigManager
{
    protected Application $app;
    protected ConfigRepository $config;
    protected array $drivers = [];
    protected array $resolvedTree = []; // For debugging

    public function __construct(Application $app, ConfigRepository $config)
    {
        $this->app = $app;
        $this->config = $config;

        $this->registerDefaultDrivers();
    }

    protected function registerDefaultDrivers(): void
    {
        $this->drivers = [
            'php'      => Drivers\PhpDriver::class,
            'json'     => Drivers\JsonDriver::class,
            'yaml'     => Drivers\YamlDriver::class,
            'database' => Drivers\DatabaseDriver::class,
        ];
    }

    public function extend(string $name, string $driverClass): void
    {
        $this->drivers[$name] = $driverClass;
    }

    public function load(): void
    {
        $cachePath = $this->config->get('dynamic-config.cache_file', base_path('bootstrap/cache/dynamic_config.php'));
        $cache = new ConfigCache($cachePath);

        if ($cache->exists()) {
            $mergedConfig = $cache->load();
        } else {
            $mergedConfig = $this->buildConfig();
        }

        $this->injectIntoLaravel($mergedConfig);
    }

    public function cache(): void
    {
        $cachePath = $this->config->get('dynamic-config.cache_file', base_path('bootstrap/cache/dynamic_config.php'));
        $cache = new ConfigCache($cachePath);

        $mergedConfig = $this->buildConfig();
        $cache->write($mergedConfig);
    }

    public function clearCache(): void
    {
        $cachePath = $this->config->get('dynamic-config.cache_file', base_path('bootstrap/cache/dynamic_config.php'));
        $cache = new ConfigCache($cachePath);
        $cache->clear();
    }

    public function buildConfig(): array
    {
        $sources = $this->config->get('dynamic-config.sources', []);
        
        // Sort by priority ascending (so higher priority merges later and overrides)
        usort($sources, fn($a, $b) => ($a['priority'] ?? 0) <=> ($b['priority'] ?? 0));

        $strategyName = $this->config->get('dynamic-config.merge_strategy', 'deep');
        $merger = current(array_filter([
            $this->app->bound(MergeStrategy::class) ? $this->app->make(MergeStrategy::class) : null,
            new MergeEngine($strategyName)
        ]));

        $mergedConfig = [];
        $this->resolvedTree = [];

        foreach ($sources as $source) {
            $driverName = $source['driver'] ?? null;
            
            if (!$driverName || !isset($this->drivers[$driverName])) {
                continue;
            }

            $driverClass = $this->drivers[$driverName];
            $driver = $this->app->make($driverClass);

            if ($driver instanceof ConfigDriver) {
                $data = $driver->load($source);

                $namespace = $source['namespace'] ?? null;
                if ($namespace) {
                    $data = [$namespace => $data];
                }

                $this->resolvedTree[$driverName][] = $data;
                $mergedConfig = $merger->merge($mergedConfig, $data);
            }
        }

        $this->resolvedTree['final'] = $mergedConfig;

        return $mergedConfig;
    }

    protected function injectIntoLaravel(array $mergedConfig): void
    {
        foreach ($mergedConfig as $key => $value) {
            $existing = $this->config->get($key);
            
            if (is_array($existing) && is_array($value)) {
                // If the root key already exists and both are arrays, deep merge them so we don't completely wipe out Laravel's default config
                $merger = new MergeEngine('deep');
                $merged = $merger->merge($existing, $value);
                $this->config->set($key, $merged);
            } else {
                $this->config->set($key, $value);
            }
        }
    }

    public function getResolvedTree(): array
    {
        return $this->resolvedTree;
    }
}
