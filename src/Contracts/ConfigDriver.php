<?php

namespace Imran\DynamicConfig\Contracts;

interface ConfigDriver
{
    /**
     * Load configuration data based on the provided source config.
     *
     * @param array $sourceConfig The configuration array for this specific source
     * @return array The loaded configuration data
     */
    public function load(array $sourceConfig): array;
}
