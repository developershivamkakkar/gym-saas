<?php

return [

    'default' => env('DB_CONNECTION', 'master'),

    'connections' => [

        // ── MASTER DATABASE (MySQL or SQLite) ─────────────────────────────
        'master' => env('DB_CONNECTION_DRIVER', 'sqlite') === 'sqlite' ? [
            'driver'                  => 'sqlite',
            'url'                     => env('DB_URL'),
            'database'                => env('DB_DATABASE', database_path('fitcore_master.sqlite')),
            'prefix'                  => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ] : [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'fitcore_master'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict'    => true,
            'engine'    => null,
        ],

        // ── TENANT / SHARD DATABASE ────────────────────────────────────────
        // Config is REPLACED at runtime by TenantResolutionMiddleware
        'tenant' => env('DB_CONNECTION_DRIVER', 'sqlite') === 'sqlite' ? [
            'driver'                  => 'sqlite',
            'database'                => database_path(env('DB_SHARD_DATABASE', 'fitcore_shard_01').'.sqlite'),
            'prefix'                  => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ] : [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_SHARD_DATABASE', 'fitcore_shard_01'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict'    => true,
            'engine'    => null,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];
