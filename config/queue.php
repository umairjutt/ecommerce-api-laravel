<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'redis' => ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default', 'retry_after' => 90, 'block_for' => null],
    ],
    'failed' => ['driver' => env('QUEUE_FAILED_DRIVER', 'null')],
];
