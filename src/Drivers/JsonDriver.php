<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Illuminate\Support\Facades\Log;

class JsonDriver implements ConfigDriver
{
    public function load(array $sourceConfig): array
    {
        $path = $sourceConfig['path'] ?? null;

        if (!$path || !file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            Log::warning("[dynamic-config] JsonDriver could not read file [{$path}].");
            return [];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("[dynamic-config] JsonDriver failed to parse [{$path}]: " . json_last_error_msg());
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
