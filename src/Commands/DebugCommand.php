<?php

namespace Imran\DynamicConfig\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Imran\DynamicConfig\ConfigManager;

class DebugCommand extends Command
{
    protected $signature = 'dynamic-config:debug
        {key? : The configuration key to inspect (dot notation)}
        {--reveal : Print sensitive-looking values (password/secret/token/key) in full instead of redacting them}';

    protected $description = 'Debug how configuration values were resolved across different sources';

    /**
     * Last segment patterns treated as sensitive and redacted by default.
     */
    protected const SENSITIVE_PATTERNS = ['*password*', '*secret*', '*token*', '*key*', '*credential*'];

    public function handle(ConfigManager $manager): int
    {
        // We need to build the config to get the resolution tree
        // The service provider might have skipped this if cache was present
        $manager->buildConfig();
        $tree = $manager->getResolvedTree();

        $key = $this->argument('key');

        if ($key) {
            $this->info("Inspecting key: {$key}");
            $this->line('');

            foreach ($tree as $driverName => $dataLists) {
                if ($driverName === 'final') {
                    continue;
                }

                foreach ($dataLists as $index => $data) {
                    $value = Arr::get($data, $key);
                    if ($value !== null) {
                        $this->line("├── <fg=cyan>{$driverName}[" . ($index+1) . "]</>: " . $this->formatValue($key, $value));
                    }
                }
            }

            $finalValue = Arr::get($tree['final'] ?? [], $key);
            if ($finalValue !== null) {
                $this->line("└── <fg=green>final</>: " . $this->formatValue($key, $finalValue));
            } else {
                $this->warn("Key '{$key}' not found in dynamic configuration.");
            }

        } else {
            // Summary mode if no key provided
            $this->info('Configuration Sources Summary:');
            $this->table(
                ['Driver', 'Loaded Configurations'],
                collect($tree)->except('final')->map(function ($items, $driver) {
                    return [$driver, count($items)];
                })->toArray()
            );

            $this->info('Total final keys merged: ' . count($tree['final'] ?? []));
        }

        return self::SUCCESS;
    }

    protected function formatValue(string $key, $value): string
    {
        if ($this->isSensitive($key) && !$this->option('reveal')) {
            return '<fg=yellow>[redacted, pass --reveal to show]</>';
        }

        if (is_array($value)) {
            // The requested key itself might be a parent (e.g. "services.stripe")
            // whose children include a sensitive leaf (e.g. "secret"). Redact
            // those recursively too, or --reveal would be bypassable simply by
            // asking for the parent instead of the exact sensitive key.
            if (!$this->option('reveal')) {
                $value = $this->redactSensitiveKeys($value);
            }

            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    protected function redactSensitiveKeys(array $value): array
    {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->redactSensitiveKeys($v);
            } elseif (is_string($k) && $this->isSensitive($k)) {
                $value[$k] = '[redacted]';
            }
        }

        return $value;
    }

    protected function isSensitive(string $key): bool
    {
        $lastSegment = Str::afterLast($key, '.');

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (Str::is($pattern, Str::lower($lastSegment))) {
                return true;
            }
        }

        return false;
    }
}
