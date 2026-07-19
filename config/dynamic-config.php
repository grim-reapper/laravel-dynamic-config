<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Merge Strategy
    |--------------------------------------------------------------------------
    |
    | Define the default merge strategy used when combining configurations from
    | multiple sources.
    | Options: 'deep' (recursive, key-by-key merge - the sane default),
    |          'replace' (array_replace_recursive - numeric keys overwrite by index),
    |          'append' (array_merge_recursive - numeric keys are appended, not overwritten)
    |
    */
    'merge_strategy' => 'deep',

    /*
    |--------------------------------------------------------------------------
    | Configuration Cache File
    |--------------------------------------------------------------------------
    |
    | Where the compiled, merged dynamic configuration will be stored.
    | You can change this path, but bootstrap/cache is recommended.
    |
    */
    'cache_file' => base_path('bootstrap/cache/dynamic_config.php'),

    /*
    |--------------------------------------------------------------------------
    | Fail Silently
    |--------------------------------------------------------------------------
    |
    | If a source fails to load (bad DB connection, malformed file, network
    | error on the api driver, etc.) this determines whether the app should
    | keep booting without that source (true, recommended for production) or
    | let the exception bubble up and stop the request/command (false, useful
    | in local/CI so a broken source is caught immediately). Failures are
    | always logged either way.
    |
    */
    'fail_silently' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto Refresh
    |--------------------------------------------------------------------------
    |
    | When true, every request/command checks whether any file-based source
    | has changed (or the source definitions themselves changed) since
    | `dynamic-config:cache` was last run, and rebuilds live if so instead of
    | serving stale cached values. This adds a handful of filemtime() checks
    | per request, so it's meant for local/staging convenience - leave it off
    | in production and rely on `dynamic-config:cache` / `dynamic-config:clear`
    | to control when the cache updates, the same way Laravel's own
    | `config:cache` works.
    |
    */
    'auto_refresh' => false,

    /*
    |--------------------------------------------------------------------------
    | Protected Keys
    |--------------------------------------------------------------------------
    |
    | Dot-notation config paths (wildcards supported, e.g. 'database.*') that
    | dynamic sources are never allowed to set, no matter their priority. This
    | matters most for the `database` and `api` drivers: if the underlying
    | table or endpoint is ever writable through a less-trusted path (a tenant
    | settings screen, an admin panel bug, etc.), an attacker who controls a
    | row/response could otherwise override things like your app key, database
    | credentials, or session/mail configuration. Nothing is protected by
    | default - uncomment/add entries for anything dynamic sources shouldn't
    | be able to touch in your app.
    |
    */
    'protected_keys' => [
        // 'app.debug',
        // 'database.connections',
        // 'session',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protect Sensitive-Looking Keys
    |--------------------------------------------------------------------------
    |
    | When true (the default), no dynamic source may set a config path whose
    | last segment looks like a secret - password, secret, token, credential,
    | api_key, private_key, access_key, client_secret, auth_token - anywhere in
    | the tree, even if it's not listed in protected_keys above. This is a
    | safety net for the common case of forgetting to list every sensitive
    | path by hand. `app.key` and `app.cipher` are always protected regardless
    | of this setting and cannot be excepted below.
    |
    | If you deliberately use the database/api driver to rotate a specific
    | secret at runtime (a legitimate use case), add its exact dot path to
    | sensitive_key_exceptions rather than disabling this outright.
    |
    */
    'protect_sensitive_keys' => true,

    'sensitive_key_exceptions' => [
        // 'services.stripe.secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Sources
    |--------------------------------------------------------------------------
    |
    | Define all the sources where configuration values should be loaded from.
    | They will be loaded and merged in order of their 'priority'. Higher
    | priority values override lower priority values.
    |
    | Each source may also set:
    |   'namespace'    => merge this source's data under a single key instead of the root.
    |   'environments' => only load this source when the app is running in one
    |                     of the listed environments, e.g. ['production', 'staging'].
    |
    */
    'sources' => [

        [
            'driver'   => 'json',
            'priority' => 5,
            'path'     => storage_path('configs/app.json'),
            // 'namespace' => 'tenant', // Optional: merge these values under a specific namespace
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
            // 'connection' => 'mysql', // Optional: specify connection
        ],

        /*
        [
            'driver'      => 'api',
            'priority'    => 30,
            'url'         => env('CONFIG_SERVER'),
            'token'       => env('CONFIG_TOKEN'),
            'timeout'     => 5,
            'environments' => ['production'],
        ],
        */

        /*
        | Loading Laravel's own config/ directory back in through the 'php'
        | driver is intentionally NOT enabled by default: it re-parses every
        | config file on every uncached request, and if the app has run
        | `php artisan config:cache`, `env()` calls inside those files will
        | resolve against a process that never loaded .env, silently pulling
        | in nulls/defaults instead of your real values. Only add a 'php'
        | source pointed at your own dedicated dynamic-config directory.
        [
            'driver'   => 'php',
            'priority' => 1,
            'path'     => storage_path('configs/overrides'),
        ],
        */

    ],

];
