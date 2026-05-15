<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseDriver implements ConfigDriver
{
    public function load(array $sourceConfig): array
    {
        $table = $sourceConfig['table'] ?? 'app_configs';
        $connection = $sourceConfig['connection'] ?? null;

        // Check if table exists to prevent errors before migration
        if (!Schema::connection($connection)->hasTable($table)) {
            return [];
        }

        $records = DB::connection($connection)->table($table)->get();

        $config = [];
        foreach ($records as $record) {
            $key = $record->key ?? null;
            $value = $record->value ?? null;
            
            if ($key === null) continue;

            // Optional: Support JSON decoding if value is a JSON string
            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            // Set using dot notation if needed, but here we just build a flat array
            // The MergeEngine will handle deep merging, but standard config expects nested arrays.
            // Let's explode the key by dot and build the array structure.
            $this->setArrayValue($config, $key, $value);
        }

        return $config;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     * (Similar to Arr::set() in Laravel)
     */
    protected function setArrayValue(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}
