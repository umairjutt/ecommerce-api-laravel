<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'Shop API',
    'version' => '1.0.0',
    'docs' => '/docs/api',
    'horizon' => '/horizon',
]));
