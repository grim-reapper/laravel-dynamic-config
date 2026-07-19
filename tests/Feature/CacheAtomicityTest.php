<?php

namespace Imran\DynamicConfig\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Imran\DynamicConfig\Cache\ConfigCache;

class CacheAtomicityTest extends TestCase
{
    protected string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = sys_get_temp_dir() . '/dynamic_config_cache_test_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFile);
        parent::tearDown();
    }

    public function test_write_and_load_roundtrip_with_signature()
    {
        $cache = new ConfigCache($this->cacheFile);

        $this->assertTrue($cache->write(['app' => ['name' => 'Test']], 'sig-123'));
        $this->assertTrue($cache->exists());
        $this->assertEquals(['app' => ['name' => 'Test']], $cache->load());

        $payload = $cache->loadPayload();
        $this->assertEquals('sig-123', $payload['signature']);
        $this->assertEquals(['app' => ['name' => 'Test']], $payload['config']);
    }

    public function test_write_does_not_leave_temp_files_behind()
    {
        $cache = new ConfigCache($this->cacheFile);
        $cache->write(['a' => 1], 'sig');

        $directory = dirname($this->cacheFile);
        $leftovers = glob($directory . '/.*' . basename($this->cacheFile) . '*.tmp');

        $this->assertEmpty($leftovers);
    }

    public function test_clear_removes_file()
    {
        $cache = new ConfigCache($this->cacheFile);
        $cache->write(['a' => 1]);
        $this->assertTrue($cache->exists());

        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->exists());
    }

    public function test_load_handles_legacy_plain_array_cache_format()
    {
        file_put_contents($this->cacheFile, '<?php return ' . var_export(['app' => ['name' => 'Legacy']], true) . ';');

        $cache = new ConfigCache($this->cacheFile);
        $this->assertEquals(['app' => ['name' => 'Legacy']], $cache->load());

        $payload = $cache->loadPayload();
        $this->assertNull($payload['signature']);
        $this->assertEquals(['app' => ['name' => 'Legacy']], $payload['config']);
    }
}
