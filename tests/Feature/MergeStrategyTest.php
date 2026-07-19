<?php

namespace Imran\DynamicConfig\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Imran\DynamicConfig\Merge\MergeEngine;

class MergeStrategyTest extends TestCase
{
    public function test_deep_merge_recurses_into_nested_arrays()
    {
        $engine = new MergeEngine('deep');
        $result = $engine->merge(
            ['a' => ['x' => 1, 'y' => 2]],
            ['a' => ['y' => 20, 'z' => 3]]
        );

        $this->assertEquals(['a' => ['x' => 1, 'y' => 20, 'z' => 3]], $result);
    }

    public function test_deep_merge_replaces_list_arrays_wholesale_instead_of_by_index()
    {
        $engine = new MergeEngine('deep');
        $result = $engine->merge(
            ['providers' => ['ProviderA', 'ProviderB', 'ProviderC']],
            ['providers' => ['ProviderX']]
        );

        // A shorter overriding list must fully replace the base list, not
        // merge index 0 only and leave ProviderB/ProviderC behind.
        $this->assertEquals(['providers' => ['ProviderX']], $result);
    }

    public function test_deep_merge_can_clear_a_list_with_an_empty_array()
    {
        $engine = new MergeEngine('deep');
        $result = $engine->merge(
            ['tags' => ['a', 'b']],
            ['tags' => []]
        );

        $this->assertEquals(['tags' => []], $result);
    }

    public function test_deep_merge_still_recurses_into_associative_sub_arrays()
    {
        $engine = new MergeEngine('deep');
        $result = $engine->merge(
            ['database' => ['connections' => ['mysql' => ['host' => 'a', 'port' => 3306]]]],
            ['database' => ['connections' => ['mysql' => ['host' => 'b']]]]
        );

        $this->assertEquals(
            ['database' => ['connections' => ['mysql' => ['host' => 'b', 'port' => 3306]]]],
            $result
        );
    }

    public function test_replace_strategy_uses_array_replace_recursive_semantics()
    {
        $engine = new MergeEngine('replace');
        $result = $engine->merge(
            ['list' => ['a', 'b', 'c']],
            ['list' => ['x']]
        );

        // array_replace_recursive overwrites entries by numeric index
        $this->assertEquals(['list' => ['x', 'b', 'c']], $result);
    }

    public function test_append_strategy_uses_array_merge_recursive_semantics()
    {
        $engine = new MergeEngine('append');
        $result = $engine->merge(
            ['list' => ['a']],
            ['list' => ['b']]
        );

        // array_merge_recursive appends numeric-keyed values rather than overwriting them
        $this->assertEquals(['list' => ['a', 'b']], $result);
    }

    public function test_unknown_strategy_name_falls_back_to_deep_merge()
    {
        $engine = new MergeEngine('some-unrecognized-strategy');
        $result = $engine->merge(['a' => 1], ['b' => 2]);

        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }
}
