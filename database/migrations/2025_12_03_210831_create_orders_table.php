<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->timestamp('date_time')->useCurrent();
            
            // Customer information
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_address')->nullable();
            
            // Order details
            $table->enum('status', ['Pending', 'Processing', 'Delivered', 'Cancelled'])->default('Pending');
            
            // Payment information
            $table->enum('payment_method', ['cash', 'card', 'online', 'bkash', 'nagad', 'rocket'])->default('cash');
            $table->enum('payment_status', ['pending', 'paid', 'partial'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            
            // Financial details
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('shipping_charge', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            
            // Additional information
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['invoice_number', 'status', 'date_time']);
            $table->index(['customer_name', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};