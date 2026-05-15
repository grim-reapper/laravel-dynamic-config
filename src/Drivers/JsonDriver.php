<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;

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
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
