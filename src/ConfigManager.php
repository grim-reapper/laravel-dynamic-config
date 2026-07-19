<?php

namespace Imran\DynamicConfig;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Imran\DynamicConfig\Contracts\MergeStrategy;
use Imran\DynamicConfig\Merge\MergeEngine;
use Imran\DynamicConfig\Cache\ConfigCache;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ConfigManager
{
    protected Application $app;
    protected ConfigRepository $config;
    protected array $drivers = [];
    protected array $resolvedTree = []; // For debugging

    /**
     * Paths no dynamic source may ever set, under any configuration. Laravel's
     * encryption key/cipher underpin every encrypted cookie, session, and
     * Crypt::encrypt() value in the app - there is no legitimate reason for a
     * runtime config source to change them, and doing so silently would break
     * decryption of everything already encrypted. Not configurable, no
     * exceptions list applies to these.
     */
    protected const ALWAYS_PROTECTED_KEYS = ['app.key', 'app.cipher'];

    /**
     * Substring terms checked against the last path segment (e.g. "secret" in
     * "services.stripe.secret") to catch secret-looking values by name alone,
     * regardless of whether the app author remembered to list them in
     * protected_keys. Deliberately narrower than the debug command's display
     * redaction list (no bare "key") because this list *blocks* dynamic writes
     * rather than just hiding them in a CLI dump - a false positive here
     * silently breaks a legitimate override instead of just over-hiding output.
     */
    protected const SENSITIVE_KEY_TERMS = [
        'password', 'secret', 'token', 'credential',
        'api_key', 'secret_key', 'private_key', 'access_key',
        'client_secret', 'auth_token',
    ];

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
            'api'      => Drivers\ApiDriver::class,
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
            $payload = $cache->loadPayload();
            $mergedConfig = $payload['config'];

            // Cheap dev-time convenience: if enabled, detect when the underlying
            // sources changed since the cache was built and rebuild live instead
            // of serving stale values. Off by default to keep production reads
            // to a single `require` with no extra stat() calls.
            if ($this->config->get('dynamic-config.auto_refresh', false) && $payload['signature'] !== null) {
                $sources = $this->config->get('dynamic-config.sources', []);
                $currentSignature = $this->computeSourcesSignature($sources);

                if ($currentSignature !== $payload['signature']) {
                    $mergedConfig = $this->buildConfig();
                }
            }
        } else {
            $mergedConfig = $this->buildConfig();
        }

        $this->injectIntoLaravel($mergedConfig);
    }

    public function cache(): void
    {
        $cachePath = $this->config->get('dynamic-config.cache_file', base_path('bootstrap/cache/dynamic_config.php'));
        $cache = new ConfigCache($cachePath);

        $sources = $this->config->get('dynamic-config.sources', []);
        $mergedConfig = $this->buildConfig();
        $signature = $this->computeSourcesSignature($sources);

        if (!$cache->write($mergedConfig, $signature)) {
            throw new \RuntimeException("Failed to write dynamic configuration cache to [{$cachePath}]. Check that the directory exists and is writable.");
        }
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
        $merger = $this->app->bound(MergeStrategy::class)
            ? $this->app->make(MergeStrategy::class)
            : new MergeEngine($strategyName);

        $protectedKeys = $this->config->get('dynamic-config.protected_keys', []);
        $failSilently = $this->config->get('dynamic-config.fail_silently', true);

        $mergedConfig = [];
        $this->resolvedTree = [];

        foreach ($sources as $source) {
            $driverName = $source['driver'] ?? null;

            if (!$driverName) {
                continue;
            }

            if (!isset($this->drivers[$driverName])) {
                Log::warning("[dynamic-config] Unknown driver [{$driverName}] referenced in a config source; skipping it.", [
                    'source' => $source,
                ]);
                continue;
            }

            // Optional per-source environment scoping, e.g. 'environments' => ['production'].
            $environments = $source['environments'] ?? null;
            if ($environments && !$this->app->environment((array) $environments)) {
                continue;
            }

            $driverClass = $this->drivers[$driverName];

            try {
                $driver = $this->app->make($driverClass);

                if (!$driver instanceof ConfigDriver) {
                    continue;
                }

                $data = $driver->load($source);
            } catch (\Throwable $e) {
                Log::warning("[dynamic-config] Source [{$driverName}] failed to load and was skipped: {$e->getMessage()}", [
                    'source' => $source,
                    'exception' => $e,
                ]);

                if (!$failSilently) {
                    throw $e;
                }

                continue;
            }

            $namespace = $source['namespace'] ?? null;
            if ($namespace) {
                $data = [$namespace => $data];
            }

            // First line of defense: strip anything this source explicitly
            // targets by its exact (or nested-under) protected path. This is
            // NOT sufficient on its own - see enforceProtectedPaths() in
            // injectIntoLaravel() for why a second, authoritative pass is
            // required - but it keeps obviously-bad values out of the
            // resolved tree shown by `dynamic-config:debug`.
            $data = $this->stripProtectedKeys($data, $protectedKeys);

            $this->resolvedTree[$driverName][] = $data;
            $mergedConfig = $merger->merge($mergedConfig, $data);
        }

        $this->resolvedTree['final'] = $mergedConfig;

        return $mergedConfig;
    }

    protected function stripProtectedKeys(array $data, array $protectedKeys): array
    {
        $flat = Arr::dot($data);
        $exceptions = $this->config->get('dynamic-config.sensitive_key_exceptions', []);
        $checkSensitive = $this->config->get('dynamic-config.protect_sensitive_keys', true);

        foreach (array_keys($flat) as $key) {
            if ($this->isAlwaysProtectedPath($key)
                || $this->matchesProtectedPattern($key, $protectedKeys)
                || ($checkSensitive && !in_array($key, $exceptions, true) && $this->isSensitiveLeaf($key))
            ) {
                unset($flat[$key]);
            }
        }

        return Arr::undot($flat);
    }

    /**
     * The authoritative protection pass. Stripping each source's contribution
     * before merging (stripProtectedKeys above) only catches a source that
     * targets the protected path directly - it can't catch a source that
     * overwrites a shallower ancestor with a scalar or list value (e.g. a
     * single DB row with key="app" clobbering the whole app.* tree, which
     * would destroy app.key without ever producing an "app.key" leaf for the
     * pre-merge check to see). So instead of trying to detect that in the
     * incoming data's shape, this snapshots the real value at every protected
     * path *before* the merge is applied to Laravel's config, then forces
     * those exact paths back to their snapshotted value *after* - regardless
     * of what happened in between.
     */
    protected function enforceProtectedPaths(array $mergedConfig): array
    {
        $protectedKeys = $this->config->get('dynamic-config.protected_keys', []);
        $checkSensitive = $this->config->get('dynamic-config.protect_sensitive_keys', true);
        $exceptions = $this->config->get('dynamic-config.sensitive_key_exceptions', []);

        $isProtected = function (string $key) use ($protectedKeys, $checkSensitive, $exceptions): bool {
            return $this->isAlwaysProtectedPath($key)
                || $this->matchesProtectedPattern($key, $protectedKeys)
                || ($checkSensitive && !in_array($key, $exceptions, true) && $this->isSensitiveLeaf($key));
        };

        $snapshot = [];

        foreach (array_keys($mergedConfig) as $root) {
            $before = Arr::dot([$root => $this->config->get($root)]);
            $incoming = Arr::dot([$root => $mergedConfig[$root]]);

            foreach (array_unique(array_merge(array_keys($before), array_keys($incoming))) as $key) {
                if ($isProtected($key)) {
                    $snapshot[$key] = $before[$key] ?? null;
                }
            }
        }

        return $snapshot;
    }

    protected function isAlwaysProtectedPath(string $key): bool
    {
        foreach (self::ALWAYS_PROTECTED_KEYS as $pattern) {
            if ($key === $pattern || str_starts_with($key, $pattern . '.')) {
                return true;
            }
        }

        return false;
    }

    protected function matchesProtectedPattern(string $key, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($key === $pattern || str_starts_with($key, $pattern . '.') || Str::is($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    protected function isSensitiveLeaf(string $key): bool
    {
        $lastSegment = Str::lower(Str::afterLast($key, '.'));

        foreach (self::SENSITIVE_KEY_TERMS as $term) {
            if (Str::contains($lastSegment, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a signature representing the current state of all file-based
     * sources (plus the source definitions themselves) so a cached config can
     * be compared against it to detect staleness.
     */
    protected function computeSourcesSignature(array $sources): string
    {
        $fingerprint = [];

        foreach ($sources as $source) {
            $path = $source['path'] ?? null;
            $mtime = null;

            if ($path && file_exists($path)) {
                $mtime = is_dir($path) ? $this->directoryMaxMtime($path) : @filemtime($path);
            }

            $fingerprint[] = [$source, $mtime];
        }

        return md5(serialize($fingerprint));
    }

    protected function directoryMaxMtime(string $path): ?int
    {
        $max = null;

        foreach (glob(rtrim($path, '/\\') . '/*.php') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && ($max === null || $mtime > $max)) {
                $max = $mtime;
            }
        }

        return $max;
    }

    protected function injectIntoLaravel(array $mergedConfig): void
    {
        $snapshot = $this->enforceProtectedPaths($mergedConfig);

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

        // Force every protected path back to its pre-injection value, no
        // matter what the merge above just did to it or its ancestors.
        foreach ($snapshot as $key => $originalValue) {
            $this->config->set($key, $originalValue);
        }
    }

    public function getResolvedTree(): array
    {
        return $this->resolvedTree;
    }
}
