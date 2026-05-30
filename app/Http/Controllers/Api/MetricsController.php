<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

/**
 * @group Observability
 *
 * Prometheus text-format metrics scrape target. Exposes business and cache
 * gauges/counters so the API can be wired into Grafana with zero extra agents.
 * @unauthenticated
 */
class MetricsController extends Controller
{
    public function index(): Response
    {
        $now = now();

        $ordersTotal = Order::count();
        $ordersLastMinute = Order::where('created_at', '>=', $now->copy()->subMinute())->count();
        $revenueCents = (int) Order::whereIn('status', [
            Order::STATUS_PAID,
            Order::STATUS_FULFILLED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
        ])->sum('total_cents');

        $byStatus = Order::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        [$hits, $misses] = $this->cacheCounters();
        $total = $hits + $misses;
        $hitRatio = $total > 0 ? round($hits / $total, 4) : 0.0;

        $lines = [];
        $lines[] = '# HELP shop_orders_total Total number of orders created.';
        $lines[] = '# TYPE shop_orders_total counter';
        $lines[] = "shop_orders_total {$ordersTotal}";

        $lines[] = '# HELP shop_orders_per_minute Orders created in the last 60 seconds.';
        $lines[] = '# TYPE shop_orders_per_minute gauge';
        $lines[] = "shop_orders_per_minute {$ordersLastMinute}";

        $lines[] = '# HELP shop_revenue_cents_total Captured revenue in minor currency units.';
        $lines[] = '# TYPE shop_revenue_cents_total counter';
        $lines[] = "shop_revenue_cents_total {$revenueCents}";

        $lines[] = '# HELP shop_orders_by_status Order count grouped by status.';
        $lines[] = '# TYPE shop_orders_by_status gauge';
        foreach ($byStatus as $status => $count) {
            $lines[] = sprintf('shop_orders_by_status{status="%s"} %d', $status, $count);
        }

        $lines[] = '# HELP shop_cache_hit_ratio Ratio of product cache hits to total lookups.';
        $lines[] = '# TYPE shop_cache_hit_ratio gauge';
        $lines[] = "shop_cache_hit_ratio {$hitRatio}";

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    /**
     * Read cache hit/miss counters maintained in Redis. Falls back to zeros
     * when Redis is unavailable so /metrics never 500s.
     *
     * @return array{0:int,1:int}
     */
    private function cacheCounters(): array
    {
        try {
            $hits = (int) (Redis::get('metrics:cache:hits') ?? 0);
            $misses = (int) (Redis::get('metrics:cache:misses') ?? 0);
        } catch (\Throwable) {
            return [0, 0];
        }

        return [$hits, $misses];
    }
}
