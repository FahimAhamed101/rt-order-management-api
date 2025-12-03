<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('order_products', 'purchase_price')) {
                $table->decimal('purchase_price', 10, 2)->default(0)->after('sale_price');
            }
            
            // Also check for other potentially missing columns
            $columnsToCheck = [
                'profit' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2],
                'sub_total' => ['type' => 'decimal', 'precision' => 12, 'scale' => 2],
            ];
            
            foreach ($columnsToCheck as $column => $config) {
                if (!Schema::hasColumn('order_products', $column)) {
                    $table->decimal($column, $config['precision'], $config['scale'])->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            // You can optionally remove the column in down method
            // $table->dropColumn('purchase_price');
        });
    }
};