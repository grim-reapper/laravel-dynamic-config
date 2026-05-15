<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Merge Strategy
    |--------------------------------------------------------------------------
    |
    | Define the default merge strategy used when combining configurations from
    | multiple sources.
    | Options: 'deep', 'replace', 'recursive', 'append'
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
    | Configuration Sources
    |--------------------------------------------------------------------------
    |
    | Define all the sources where configuration values should be loaded from.
    | They will be loaded and merged in order of their 'priority'. Higher
    | priority values override lower priority values.
    |
    */
    'sources' => [

        [
            'driver'   => 'php',
            'priority' => 1,
            'path'     => base_path('config'), // Assuming it could load standard configs if needed
        ],

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
            'driver'   => 'api',
            'priority' => 30,
            'url'      => env('CONFIG_SERVER'),
            'token'    => env('CONFIG_TOKEN'),
        ],
        */

    ],

];
