<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Stock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['Pending', 'Processing', 'Delivered', 'Cancelled'];
        $paymentMethods = ['cash', 'card', 'online', 'bkash', 'nagad'];
        $paymentStatuses = ['pending', 'paid', 'partial'];
        
        $customers = [
            ['John Doe', 'john@example.com', '01711223344'],
            ['Jane Smith', 'jane@example.com', '01722334455'],
            ['Bob Johnson', 'bob@example.com', '01733445566'],
            ['Alice Brown', 'alice@example.com', '01744556677'],
            ['Charlie Wilson', 'charlie@example.com', '01755667788'],
        ];

        for ($i = 1; $i <= 20; $i++) {
            $customer = $customers[array_rand($customers)];
            $status = $statuses[array_rand($statuses)];
            
            $order = Order::create([
                'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'customer_name' => $customer[0],
                'customer_email' => $customer[1],
                'customer_phone' => $customer[2],
                'customer_address' => 'Address ' . $i . ', City, Country',
                'date_time' => now()->subDays(rand(1, 60)),
                'status' => $status,
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'sub_total' => 0,
                'discount' => rand(0, 1000),
                'tax' => rand(0, 500),
                'shipping_charge' => rand(0, 200),
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'notes' => 'Test order ' . $i,
            ]);

            // Add products to order
            $stocks = Stock::where('quantity', '>', 0)->inRandomOrder()->limit(rand(1, 5))->get();
            $subTotal = 0;
            $totalProfit = 0;

            foreach ($stocks as $stock) {
                $quantity = rand(1, min(3, $stock->quantity));
                
                $subTotalItem = $stock->sale_price * $quantity;
                $profitItem = ($stock->sale_price - $stock->purchase_price) * $quantity;
                
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $stock->product_id,
                    'stock_id' => $stock->id,
                    'quantity' => $quantity,
                    'sale_price' => $stock->sale_price,
                    'purchase_price' => $stock->purchase_price,
                    'sub_total' => $subTotalItem,
                    'profit' => $profitItem,
                ]);

                $subTotal += $subTotalItem;
                $totalProfit += $profitItem;
                
                // Update stock if order is not cancelled
                if ($status !== 'Cancelled') {
                    $stock->decrement('quantity', $quantity);
                }
            }

            // Calculate final amounts
            $totalAmount = $subTotal - $order->discount + $order->tax + $order->shipping_charge;
            $paidAmount = $order->payment_status === 'paid' ? $totalAmount : 
                         ($order->payment_status === 'partial' ? $totalAmount * 0.7 : 0);
            $dueAmount = $totalAmount - $paidAmount;

            $order->update([
                'sub_total' => $subTotal,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
            ]);
        }
    }
}