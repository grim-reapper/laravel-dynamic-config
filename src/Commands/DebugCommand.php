<?php

namespace Imran\DynamicConfig\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Imran\DynamicConfig\ConfigManager;

class DebugCommand extends Command
{
    protected $signature = 'dynamic-config:debug {key? : The configuration key to inspect (dot notation)}';
    protected $description = 'Debug how configuration values were resolved across different sources';

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
                        $this->line("├── <fg=cyan>{$driverName}[" . ($index+1) . "]</>: " . $this->formatValue($value));
                    }
                }
            }

            $finalValue = Arr::get($tree['final'] ?? [], $key);
            if ($finalValue !== null) {
                $this->line("└── <fg=green>final</>: " . $this->formatValue($finalValue));
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

    protected function formatValue($value): string
    {
        if (is_array($value)) {
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
}
