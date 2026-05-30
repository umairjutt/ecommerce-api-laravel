<?php

return [
    'driver' => env('SESSION_DRIVER', 'array'),
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', 'default'),
    'table' => 'sessions',
    'cookie' => 'shop_session',
    'path' => '/',
    'http_only' => true,
    'same_site' => 'lax',
];
