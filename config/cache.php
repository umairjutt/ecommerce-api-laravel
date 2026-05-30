<?php

return [
    'default' => env('CACHE_STORE', 'redis'),
    'stores' => [
        'array' => ['driver' => 'array'],
        'redis' => ['driver' => 'redis', 'connection' => 'cache', 'lock_connection' => 'default'],
        'database' => ['driver' => 'database', 'table' => 'cache'],
    ],
    'prefix' => env('CACHE_PREFIX', 'shop_cache'),
];
