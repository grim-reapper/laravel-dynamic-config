<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class YamlDriver implements ConfigDriver
{
    public function load(array $sourceConfig): array
    {
        $path = $sourceConfig['path'] ?? null;

        if (!$path || !file_exists($path)) {
            return [];
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (ParseException $e) {
            Log::warning("[dynamic-config] YamlDriver failed to parse [{$path}]: " . $e->getMessage());
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }
}
