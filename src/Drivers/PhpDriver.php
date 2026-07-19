<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Illuminate\Support\Facades\Log;

class PhpDriver implements ConfigDriver
{
    public function load(array $sourceConfig): array
    {
        $path = $sourceConfig['path'] ?? null;

        if (!$path) {
            return [];
        }

        // If it's a directory, we could load all php files (like standard Laravel)
        // If it's a file, just include it.
        if (is_file($path)) {
            return $this->requireFile($path);
        }

        if (is_dir($path)) {
            $config = [];
            foreach (glob(rtrim($path, '/\\') . '/*.php') ?: [] as $file) {
                $key = basename($file, '.php');
                $config[$key] = $this->requireFile($file);
            }
            return $config;
        }

        return [];
    }

    /**
     * `require` can throw a ParseError/TypeError (both \Throwable, not
     * \Exception) if the file is malformed or blows up at include time.
     * Isolate that per-file so one broken config file doesn't take the whole
     * source (or the app boot) down with it.
     */
    protected function requireFile(string $path): array
    {
        try {
            $config = require $path;
        } catch (\Throwable $e) {
            Log::warning("[dynamic-config] PhpDriver failed to load [{$path}]: " . $e->getMessage());
            return [];
        }

        return is_array($config) ? $config : [];
    }
}
