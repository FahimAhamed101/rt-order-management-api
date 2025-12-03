<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns to order_products table
        Schema::table('order_products', function (Blueprint $table) {
            if (!Schema::hasColumn('order_products', 'purchase_price')) {
                $table->decimal('purchase_price', 10, 2)->default(0)->after('sale_price');
            }
        });

        // Add missing columns to orders table
        Schema::table('orders', function (Blueprint $table) {
            $columnsToAdd = [
                'customer_email' => ['type' => 'string', 'length' => 255, 'nullable' => true],
                'customer_phone' => ['type' => 'string', 'length' => 20, 'nullable' => true],
                'customer_address' => ['type' => 'text', 'nullable' => true],
                'payment_date' => ['type' => 'timestamp', 'nullable' => true],
                'sub_total' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2, 'default' => 0],
                'discount' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0],
                'tax' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0],
                'shipping_charge' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => 0],
                'paid_amount' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2, 'default' => 0],
                'due_amount' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2, 'default' => 0],
            ];

            foreach ($columnsToAdd as $column => $config) {
                if (!Schema::hasColumn('orders', $column)) {
                    if ($config['type'] === 'decimal') {
                        $table->decimal($column, $config['precision'], $config['scale'])->default($config['default']);
                    } elseif ($config['type'] === 'string') {
                        $table->string($column, $config['length'])->nullable();
                    } elseif ($config['type'] === 'text') {
                        $table->text($column)->nullable();
                    } elseif ($config['type'] === 'timestamp') {
                        $table->timestamp($column)->nullable();
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // You can optionally remove columns in down method
        // Schema::table('order_products', function (Blueprint $table) {
        //     $table->dropColumn('purchase_price');
        // });
    }
};