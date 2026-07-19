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
            // Sequential/list arrays (e.g. app.providers, cors.allowed_origins,
            // a middleware group) are treated as a single value to replace
            // wholesale, not merged index-by-index. Otherwise overriding a
            // 3-item list with a 1-item list would only overwrite index 0 and
            // silently leave the other two stale entries from the base array
            // behind - which is never what "override this list" means.
            // Associative arrays (real nested config sub-trees) still merge
            // key-by-key as before.
            if (is_array($value) && !array_is_list($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
