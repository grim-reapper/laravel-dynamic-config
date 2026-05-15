<?php

namespace Imran\DynamicConfig\Commands;

use Illuminate\Console\Command;
use Imran\DynamicConfig\ConfigManager;

class ClearCommand extends Command
{
    protected $signature = 'dynamic-config:clear';
    protected $description = 'Remove the dynamic configuration cache file';

    public function handle(ConfigManager $manager): int
    {
        $this->info('Clearing dynamic configuration cache...');

        try {
            $manager->clearCache();
            $this->info('Dynamic configuration cache cleared!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to clear cache: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
