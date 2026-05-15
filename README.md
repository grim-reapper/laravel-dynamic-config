# Laravel Dynamic Config 🚀

[![Latest Version on Packagist](https://img.shields.io/packagist/v/imran/laravel-dynamic-config.svg?style=flat-square)](https://packagist.org/packages/imran/laravel-dynamic-config)
[![Total Downloads](https://img.shields.io/packagist/dt/imran/laravel-dynamic-config.svg?style=flat-square)](https://packagist.org/packages/imran/laravel-dynamic-config)
[![License](https://img.shields.io/packagist/l/imran/laravel-dynamic-config.svg?style=flat-square)](https://packagist.org/packages/imran/laravel-dynamic-config)

**Take control of your Laravel application configuration at runtime.** Load, merge, and override configuration from multiple sources like Databases, YAML, JSON, and PHP files without redeploying your app.

---

## 🌟 Why Laravel Dynamic Config?

In modern web applications, configuration often needs to be more than just static `.env` or PHP files. Whether it's white-labeling settings, feature flags stored in a database, or environment-specific overrides in YAML/JSON, managing these can become a nightmare.

### The Problem
- **Deployment Overhead**: Changing a simple config value often requires a full CI/CD pipeline run.
- **Database Settings**: Storing settings in the database but struggling to inject them seamlessly into Laravel's `config()` system.
- **Complexity**: Merging multiple configuration sources manually is error-prone and hard to maintain.

### The Solution
**Laravel Dynamic Config** provides a robust, priority-based configuration engine that hooks directly into Laravel's core. It allows you to define multiple "sources" of truth and merges them intelligently, giving you a unified `config()` API while keeping your application fast with built-in caching.

---

## ✨ Features

- 🛠 **Multiple Drivers**: Out-of-the-box support for **Database**, **YAML**, **JSON**, and **PHP**.
- 🧬 **Priority Merging**: Define which source takes precedence. Higher priority sources override lower ones.
- 📦 **Namespace Support**: Load configuration into specific keys (e.g., `services.stripe`) or the root config.
- ⚡ **High Performance**: Custom caching engine ensures your app remains blazing fast.
- 💻 **CLI Power**: Artisan commands for debugging, caching, and clearing configuration.
- 🧩 **Extensible**: Easily add your own custom drivers.

---

## 🚀 Installation

You can install the package via composer:

```bash
composer require imran/laravel-dynamic-config
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Imran\DynamicConfig\DynamicConfigServiceProvider"
```

> **Note**: If you intend to use YAML configurations, ensure you have the symfony/yaml package installed:
> `composer require symfony/yaml`

### Configuration

Define your sources in `config/dynamic-config.php`:

```php
return [
    'merge_strategy' => 'deep', // deep, replace, append
    'cache_file' => base_path('bootstrap/cache/dynamic_config.php'),
    
    'sources' => [
        [
            'driver'   => 'json',
            'priority' => 5,
            'path'     => storage_path('configs/app.json'),
        ],
        [
            'driver'   => 'yaml',
            'priority' => 10,
            'path'     => storage_path('configs/features.yaml'),
        ],
        [
            'driver'   => 'database',
            'priority' => 20,
            'table'    => 'app_configs',
        ],
    ],
];
```

## Artisan Commands

### Caching Configurations

Because dynamic configurations bypass Laravel's native `config:cache`, you must use this package's cache commands for production performance.

```bash
php artisan dynamic-config:cache
```

To clear the generated cache:

```bash
php artisan dynamic-config:clear
```

### Debugging Resolutions

Ever wonder where a config value came from? Use the debug command to see a resolution tree for a specific key:

```bash
php artisan dynamic-config:debug app.name
```

**Output:**
```text
Inspecting key: app.name

├── json[1]: "My App"
├── yaml[1]: "Enterprise App"
└── final: "Enterprise App"
```

## Extending Drivers

You can easily add your own drivers (e.g., API, Redis, Vault) by creating a class that implements `Imran\DynamicConfig\Contracts\ConfigDriver` and extending the `ConfigManager` inside your `AppServiceProvider`.

```php
use Imran\DynamicConfig\ConfigManager;

public function boot()
{
    $this->app->make(ConfigManager::class)->extend('redis', RedisDriver::class);
}
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
