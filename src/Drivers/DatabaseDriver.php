<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DatabaseDriver implements ConfigDriver
{
    public function load(array $sourceConfig): array
    {
        $table = $sourceConfig['table'] ?? 'app_configs';
        $connection = $sourceConfig['connection'] ?? null;

        try {
            // Check if table exists to prevent errors before migration
            if (!Schema::connection($connection)->hasTable($table)) {
                return [];
            }

            // Order deterministically: without this, two rows whose keys
            // overlap (e.g. a broad "services" blob and a specific
            // "services.stripe.secret" row) resolve in whatever order the
            // storage engine happens to return them - which SQL does not
            // guarantee without an explicit ORDER BY, and can differ between
            // engines or even between runs on the same engine. Ascending by
            // key also means a shorter/broader key is always processed
            // before its own more specific nested keys, so "most specific
            // wins" holds consistently.
            $records = DB::connection($connection)->table($table)->orderBy('key')->get();
        } catch (\Throwable $e) {
            // The connection may not be configured yet (fresh install, local
            // tooling run before `.env` is set up, etc.) - don't let that take
            // the whole application boot down.
            Log::warning("[dynamic-config] DatabaseDriver could not read table [{$table}]" . ($connection ? " on connection [{$connection}]" : '') . ': ' . $e->getMessage());
            return [];
        }

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

        $segments = explode('.', $key);

        foreach ($segments as $i => $segment) {
            if (count($segments) === 1) {
                break;
            }

            unset($segments[$i]);

            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array = &$array[$segment];
        }

        $array[array_shift($segments)] = $value;

        return $array;
    }
}
