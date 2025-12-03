<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('stock_id')->constrained()->onDelete('cascade');
            
            // Pricing information
            $table->decimal('sale_price', 10, 2);
            $table->decimal('purchase_price', 10, 2); // Add this column
            $table->decimal('sub_total', 12, 2);
            $table->decimal('profit', 12, 2);
            $table->integer('quantity')->default(1);
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['order_id', 'product_id', 'stock_id']);
            $table->index('stock_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};