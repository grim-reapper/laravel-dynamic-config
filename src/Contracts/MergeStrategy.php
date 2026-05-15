<?php

namespace Imran\DynamicConfig\Contracts;

interface MergeStrategy
{
    /**
     * Merge the new configuration data into the base configuration data.
     *
     * @param array $base The existing configuration
     * @param array $new The new configuration to merge in
     * @return array The merged configuration
     */
    public function merge(array $base, array $new): array;
}
