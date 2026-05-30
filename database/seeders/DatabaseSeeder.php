<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['guest', 'customer', 'vendor', 'admin'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@shop.test'],
            ['name' => 'Admin', 'password' => 'password']
        );
        $admin->syncRoles(['admin']);

        $customer = User::firstOrCreate(
            ['email' => 'customer@shop.test'],
            ['name' => 'Customer', 'password' => 'password']
        );
        $customer->syncRoles(['customer']);

        $categories = collect(['Electronics', 'Books', 'Clothing', 'Home', 'Sports', 'Toys',
            'Beauty', 'Grocery', 'Tools', 'Garden', 'Music', 'Auto'])
            ->map(fn ($n) => Category::firstOrCreate(['slug' => Str::slug($n)], ['name' => $n]));

        if (Product::count() < 50) {
            foreach (range(1, 200) as $i) {
                $cat = $categories->random();
                Product::create([
                    'name' => "Product {$i}",
                    'slug' => 'product-' . $i,
                    'description' => "Description for product {$i}.",
                    'sku' => 'SKU-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'price_cents' => rand(500, 50000),
                    'currency' => 'USD',
                    'stock' => rand(0, 200),
                    'category_id' => $cat->id,
                    'is_active' => true,
                    'image_url' => "https://picsum.photos/seed/{$i}/400/400",
                ]);
            }
        }

        Coupon::firstOrCreate(['code' => 'WELCOME10'], [
            'type' => 'percent', 'value' => 10, 'min_subtotal_cents' => 1000, 'is_active' => true,
        ]);
        Coupon::firstOrCreate(['code' => 'FREESHIP'], [
            'type' => 'fixed', 'value' => 500, 'min_subtotal_cents' => 2000, 'is_active' => true,
        ]);
    }
}
