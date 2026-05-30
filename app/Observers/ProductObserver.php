<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function saved(Product $product): void
    {
        $this->flush();
    }

    public function deleted(Product $product): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['products'])->flush();
        } else {
            Cache::forget('products.index');
        }
    }
}
