<?php

namespace Imran\DynamicConfig\Cache;

class ConfigCache
{
    protected string $cacheFile;

    public function __construct(string $cacheFile)
    {
        $this->cacheFile = $cacheFile;
    }

    public function exists(): bool
    {
        return file_exists($this->cacheFile);
    }

    public function load(): array
    {
        if ($this->exists()) {
            $config = require $this->cacheFile;
            return is_array($config) ? $config : [];
        }

        return [];
    }

    public function write(array $config): bool
    {
        $content = '<?php return ' . var_export($config, true) . ';' . PHP_EOL;
        
        $directory = dirname($this->cacheFile);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($this->cacheFile, $content) !== false;
    }

    public function clear(): bool
    {
        if ($this->exists()) {
            return unlink($this->cacheFile);
        }

        return true;
    }
}
