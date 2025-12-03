<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('orders', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_name');
            }
            
            if (!Schema::hasColumn('orders', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_email');
            }
            
            if (!Schema::hasColumn('orders', 'customer_address')) {
                $table->text('customer_address')->nullable()->after('customer_phone');
            }
            
            if (!Schema::hasColumn('orders', 'payment_date')) {
                $table->timestamp('payment_date')->nullable()->after('payment_status');
            }
            
            // Add any other missing columns
            $missingColumns = [
                'sub_total' => 'decimal:12,2',
                'discount' => 'decimal:10,2',
                'tax' => 'decimal:10,2',
                'shipping_charge' => 'decimal:10,2',
                'paid_amount' => 'decimal:12,2',
                'due_amount' => 'decimal:12,2',
            ];
            
            foreach ($missingColumns as $column => $type) {
                if (!Schema::hasColumn('orders', $column)) {
                    if (str_contains($type, 'decimal')) {
                        $precision = explode(':', explode(',', $type)[0])[1] ?? 12;
                        $scale = explode(',', $type)[1] ?? 2;
                        $table->decimal($column, $precision, $scale)->default(0);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // You can optionally remove columns in down method
            // $table->dropColumn(['customer_email', 'customer_phone', 'customer_address']);
        });
    }
};