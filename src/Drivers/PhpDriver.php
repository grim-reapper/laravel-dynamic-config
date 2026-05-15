<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;

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
            $config = require $path;
            return is_array($config) ? $config : [];
        }

        if (is_dir($path)) {
            $config = [];
            foreach (glob($path . '/*.php') as $file) {
                $key = basename($file, '.php');
                $config[$key] = require $file;
            }
            return $config;
        }

        return [];
    }
}
