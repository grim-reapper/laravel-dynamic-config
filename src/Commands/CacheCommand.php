<?php

namespace Imran\DynamicConfig\Commands;

use Illuminate\Console\Command;
use Imran\DynamicConfig\ConfigManager;

class CacheCommand extends Command
{
    protected $signature = 'dynamic-config:cache';
    protected $description = 'Discover and cache dynamic configuration from all configured sources';

    public function handle(ConfigManager $manager): int
    {
        $this->info('Building dynamic configuration cache...');

        try {
            $manager->cache();
            $this->info('Dynamic configuration cached successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to cache configuration: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
