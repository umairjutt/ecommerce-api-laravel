<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * @group Catalog
 *
 * Browse and search the product catalog. Responses are cached in Redis for 5
 * minutes and invalidated automatically when a product changes.
 */
class ProductController extends Controller
{
    /**
     * List products
     *
     * Paginated, filterable product listing. Results are served from a Redis
     * tagged cache keyed on the query string.
     *
     * @queryParam search string Full-text term matched against name + description. Example: shoes
     * @queryParam category string Filter by category slug. Example: electronics
     * @queryParam min_price number Minimum price in major units. Example: 10
     * @queryParam max_price number Maximum price in major units. Example: 500
     * @unauthenticated
     * @apiResourceCollection App\Http\Resources\ProductResource
     * @apiResourceModel App\Models\Product
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $key = 'products.index.' . md5(json_encode($request->query()));

        $cache = method_exists(Cache::getStore(), 'tags')
            ? Cache::tags(['products'])
            : Cache::store();

        $this->recordCache($cache->has($key));

        $products = $cache->remember($key, 300, function () use ($request) {
            return Product::query()
                ->with('category')
                ->where('is_active', true)
                ->when($request->category, fn ($q, $c) => $q->whereHas('category', fn ($qq) => $qq->where('slug', $c)))
                ->when($request->search, fn ($q, $s) => $q->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                       ->orWhere('description', 'like', "%{$s}%");
                }))
                ->when($request->min_price, fn ($q, $p) => $q->where('price_cents', '>=', $p * 100))
                ->when($request->max_price, fn ($q, $p) => $q->where('price_cents', '<=', $p * 100))
                ->orderByDesc('id')
                ->paginate(20);
        });

        return ProductResource::collection($products);
    }

    /**
     * Get a product
     *
     * Fetch a single active product by its slug, with category eager-loaded.
     *
     * @urlParam slug string required The product slug. Example: product-1
     * @unauthenticated
     * @apiResource App\Http\Resources\ProductResource
     * @apiResourceModel App\Models\Product
     */
    public function show(string $slug): ProductResource
    {
        $product = Cache::remember("product.{$slug}", 300, function () use ($slug) {
            return Product::where('slug', $slug)->with('category')->firstOrFail();
        });

        return new ProductResource($product);
    }

    /**
     * Increment Redis cache hit/miss counters consumed by /api/metrics.
     */
    private function recordCache(bool $hit): void
    {
        try {
            Redis::incr($hit ? 'metrics:cache:hits' : 'metrics:cache:misses');
        } catch (\Throwable) {
            // Metrics are best-effort; never break the request path.
        }
    }
}
