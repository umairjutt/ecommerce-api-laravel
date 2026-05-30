<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $key = 'products.index.' . md5(json_encode($request->query()));

        $cache = method_exists(Cache::getStore(), 'tags')
            ? Cache::tags(['products'])
            : Cache::store();

        $data = $cache->remember($key, 300, function () use ($request) {
            return Product::query()
                ->where('is_active', true)
                ->when($request->category, fn ($q, $c) => $q->whereHas('category', fn ($qq) => $qq->where('slug', $c)))
                ->when($request->search, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                       ->orWhere('description', 'like', "%{$s}%");
                }))
                ->when($request->min_price, fn ($q, $p) => $q->where('price_cents', '>=', $p * 100))
                ->when($request->max_price, fn ($q, $p) => $q->where('price_cents', '<=', $p * 100))
                ->orderByDesc('id')
                ->paginate(20)
                ->toArray();
        });

        return response()->json($data);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Cache::remember("product.{$slug}", 300, function () use ($slug) {
            return Product::where('slug', $slug)->with('category')->firstOrFail();
        });

        return response()->json($product);
    }
}
