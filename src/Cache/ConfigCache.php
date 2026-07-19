<?php

namespace Imran\DynamicConfig\Cache;

class ConfigCache
{
    protected string $cacheFile;

    public function __construct(string $cacheFile)
    {
        $this->cacheFile = $cacheFile;
    }

    public function exists(): bool
    {
        return file_exists($this->cacheFile);
    }

    public function load(): array
    {
        if ($this->exists()) {
            $cached = require $this->cacheFile;

            if (!is_array($cached)) {
                return [];
            }

            // Cache files written by this version wrap the merged config with
            // a signature payload; older/foreign files may just be a plain array.
            if (array_key_exists('config', $cached) && array_key_exists('signature', $cached)) {
                return is_array($cached['config']) ? $cached['config'] : [];
            }

            return $cached;
        }

        return [];
    }

    /**
     * Load the raw cache payload, including its staleness signature (if any).
     */
    public function loadPayload(): array
    {
        if (!$this->exists()) {
            return ['config' => [], 'signature' => null];
        }

        $cached = require $this->cacheFile;

        if (!is_array($cached)) {
            return ['config' => [], 'signature' => null];
        }

        if (array_key_exists('config', $cached) && array_key_exists('signature', $cached)) {
            return [
                'config' => is_array($cached['config']) ? $cached['config'] : [],
                'signature' => $cached['signature'],
            ];
        }

        return ['config' => $cached, 'signature' => null];
    }

    public function write(array $config, ?string $signature = null): bool
    {
        $payload = [
            'signature' => $signature,
            'config' => $config,
        ];

        $content = '<?php return ' . var_export($payload, true) . ';' . PHP_EOL;

        $directory = dirname($this->cacheFile);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        // Write to a temp file in the same directory and rename() into place so
        // a concurrent request never sees (and require()s) a partially written file.
        $tempFile = $directory . DIRECTORY_SEPARATOR . '.' . basename($this->cacheFile) . '.' . getmypid() . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            return false;
        }

        $moved = rename($tempFile, $this->cacheFile);

        if (!$moved) {
            @unlink($tempFile);
            return false;
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->cacheFile, true);
        }

        return true;
    }

    public function clear(): bool
    {
        if ($this->exists()) {
            return unlink($this->cacheFile);
        }

        return true;
    }
}
