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

- 🛠 **Multiple Drivers**: Out-of-the-box support for **Database**, **YAML**, **JSON**, **PHP**, and a remote **API** driver.
- 🧬 **Priority Merging**: Define which source takes precedence. Higher priority sources override lower ones.
- 📦 **Namespace Support**: Load configuration into specific keys (e.g., `services.stripe`) or the root config.
- 🌎 **Environment Scoping**: Limit a source to specific environments (e.g. only load the API driver in `production`).
- 🛡 **Protected Keys**: Block dynamic sources from ever overriding sensitive config paths (`app.key`, DB credentials, etc.).
- 🧯 **Fails Soft**: A broken source (down DB, malformed file, network error) is logged and skipped instead of crashing the app - configurable per-project.
- ⚡ **High Performance**: Custom caching engine (atomic writes) ensures your app remains blazing fast.
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
php artisan vendor:publish --tag=dynamic-config-config
```

If you plan to use the `database` driver, publish and run the migration for its default `app_configs` table (`key`/`value` columns):

```bash
php artisan vendor:publish --tag=dynamic-config-migrations
php artisan migrate
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

## Configuration Options

| Key | Default | Purpose |
|---|---|---|
| `merge_strategy` | `deep` | `deep`, `replace` (`array_replace_recursive`), or `append` (`array_merge_recursive`). |

With `deep` (the default), list-style arrays (`app.providers`, `cors.allowed_origins`, a middleware group, etc.) are replaced wholesale by whichever source sets them last, not merged index-by-index - so overriding a 3-item list with a 1-item list actually results in a 1-item list, and overriding with `[]` actually clears it. Associative arrays (`database.connections.mysql.*`, `app.*`, ...) still merge key-by-key as expected.

| `fail_silently` | `true` | Log and skip a source that fails to load instead of crashing the request/command. Set `false` in local/CI to catch broken sources immediately. |
| `auto_refresh` | `false` | Detect when a file-based source changed since the last `dynamic-config:cache` run and rebuild live instead of serving stale cache. Adds a few `filemtime()` checks per request - fine for local/staging, leave off in production. |
| `protected_keys` | `[]` | Dot-notation paths (wildcards supported) that no dynamic source may ever set. See **Security** below. |
| `protect_sensitive_keys` | `true` | Automatically block any path whose last segment looks like a secret, even if it's not in `protected_keys`. See **Security** below. |
| `sensitive_key_exceptions` | `[]` | Exact dot paths allowed to bypass `protect_sensitive_keys` for a deliberate use case (e.g. rotating one specific secret via the database driver). |
| `sources[].environments` | — | Restrict a source to specific environments, e.g. `'environments' => ['production']`. |

## Security: the `database` (and `api`) driver trust boundary

Any row in your `app_configs` table (or any value returned by an `api` source) can set **any** config path - the merge doesn't know or care where a key came from. That's fine as long as only trusted code/administrators can write to that table or endpoint. If it's ever reachable through a less-trusted path (a tenant self-service settings screen, an admin panel with a mass-assignment bug, a compromised upstream API), that becomes a path to full config takeover. Three layers guard against this:

**1. `app.key` and `app.cipher` are always protected**, unconditionally, with no config option and no exception list that can override it. There's no legitimate reason for a runtime source to change Laravel's encryption key, and doing so would silently break decryption of every cookie/session/`Crypt::encrypt()` value already out there.

**2. `protect_sensitive_keys` (on by default)** blocks any path whose last segment looks like a secret - `password`, `secret`, `token`, `credential`, `api_key`, `secret_key`, `private_key`, `access_key`, `client_secret`, `auth_token` - anywhere in the tree, without you having to list every one by hand. This applies to **every** driver, not just `database`/`api` - a `services.stripe.secret` key checked into your own `json`/`yaml`/`php` dynamic-config file is blocked by default too, since the merge has no reliable way to know a file source is "trusted" while a database source isn't. If you deliberately want to set a secret through a dynamic source (file or DB), allow just that path:

```php
'protect_sensitive_keys' => true,
'sensitive_key_exceptions' => [
    'services.stripe.secret',
],
```

**3. `protected_keys`** covers anything else you want locked down that isn't secret-named - `database.connections`, `session`, feature flags that should never move, etc:

```php
'protected_keys' => [
    'database.connections',
    'session',
],
```

Entries in both lists support `Str::is()`-style wildcards (e.g. `'mail.*'`) and also block anything nested under them (`'database.connections'` blocks `database.connections.mysql.host` too). Protection is enforced by snapshotting the real value at each protected path *before* any source is merged in, and forcing it back afterward - so a source can't get around it by overwriting a shallower ancestor (e.g. setting the whole `app` key to a single scalar) instead of the exact protected leaf.

`dynamic-config:debug` also redacts any value whose key looks sensitive (`*password*`, `*secret*`, `*token*`, `*key*`, `*credential*`) by default, recursively through nested arrays - pass `--reveal` to print it anyway.

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
