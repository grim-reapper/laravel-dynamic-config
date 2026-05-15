<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Symfony\Component\Yaml\Yaml;

class YamlDriver implements ConfigDriver
{
    public function load(array $sourceConfig): array
    {
        $path = $sourceConfig['path'] ?? null;

        if (!$path || !file_exists($path)) {
            return [];
        }

        if (!class_exists(Yaml::class)) {
            throw new \RuntimeException('Symfony YAML component is required to parse YAML files. Run "composer require symfony/yaml".');
        }

        $parsed = Yaml::parseFile($path);

        return is_array($parsed) ? $parsed : [];
    }
}
