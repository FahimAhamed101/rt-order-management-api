<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Apple iPhone 14 Pro',
                'barcode' => '8801234567890',
                'description' => 'Latest iPhone with dynamic island',
            ],
            [
                'name' => 'Samsung Galaxy S23 Ultra',
                'barcode' => '8801234567891',
                'description' => 'Flagship Android phone with S Pen',
            ],
            [
                'name' => 'Dell XPS 13 Laptop',
                'barcode' => '8801234567892',
                'description' => 'Premium ultrabook with infinity edge display',
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'barcode' => '8801234567893',
                'description' => 'Noise cancelling headphones',
            ],
            [
                'name' => 'Logitech MX Master 3S',
                'barcode' => '8801234567894',
                'description' => 'Wireless mouse for productivity',
            ],
            [
                'name' => 'Apple MacBook Pro 16"',
                'barcode' => '8801234567895',
                'description' => 'Professional laptop with M2 Pro chip',
            ],
            [
                'name' => 'Samsung 4K Smart TV',
                'barcode' => '8801234567896',
                'description' => '55" 4K UHD Smart Television',
            ],
            [
                'name' => 'Bose QuietComfort 45',
                'barcode' => '8801234567897',
                'description' => 'Wireless Bluetooth headphones',
            ],
            [
                'name' => 'Canon EOS R6',
                'barcode' => '8801234567898',
                'description' => 'Mirrorless camera professional',
            ],
            [
                'name' => 'Amazon Echo Dot 5th Gen',
                'barcode' => '8801234567899',
                'description' => 'Smart speaker with Alexa',
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'barcode' => $productData['barcode'],
                'slug' => Str::slug($productData['name']),
                'description' => $productData['description'],
            ]);

            // Create multiple stock entries for FIFO testing
            $purchasePrices = [
                rand(50000, 100000),
                rand(55000, 105000),
                rand(60000, 110000),
            ];
            
            $salePrices = [
                rand(60000, 120000),
                rand(65000, 125000),
                rand(70000, 130000),
            ];
            
            for ($i = 1; $i <= 3; $i++) {
                Stock::create([
                    'product_id' => $product->id,
                    'sku' => $productData['barcode'] . '-STOCK-' . $i,
                    'purchase_price' => $purchasePrices[$i-1],
                    'sale_price' => $salePrices[$i-1],
                    'quantity' => rand(5, 50),
                    'last_updated_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }
}