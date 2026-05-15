<?php

namespace Imran\DynamicConfig\Merge;

use Imran\DynamicConfig\Contracts\MergeStrategy;

class MergeEngine implements MergeStrategy
{
    protected string $strategy;

    public function __construct(string $strategy = 'deep')
    {
        $this->strategy = $strategy;
    }

    public function merge(array $base, array $new): array
    {
        return match ($this->strategy) {
            'replace' => array_replace_recursive($base, $new),
            'append'  => array_merge_recursive($base, $new),
            'deep'    => $this->deepMerge($base, $new),
            default   => $this->deepMerge($base, $new),
        };
    }

    protected function deepMerge(array $base, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
