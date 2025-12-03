<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'date_time',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'status',
        'payment_method',
        'payment_status',
        'payment_date',
        'sub_total',
        'discount',
        'tax',
        'shipping_charge',
        'total_amount',
        'paid_amount',
        'due_amount',
        'notes'
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'payment_date' => 'datetime',
        'sub_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    /**
     * Get the order products for the order.
     */
    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    /**
     * Get the stock logs for the order.
     */
    public function stockLogs(): HasMany
    {
        return $this->hasMany(StockLog::class);
    }

    /**
     * Generate invoice number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->invoice_number)) {
                $date = date('Ymd');
                $lastOrder = self::whereDate('created_at', today())->latest()->first();
                $sequence = $lastOrder ? (int)substr($lastOrder->invoice_number, -5) + 1 : 1;
                $order->invoice_number = 'INV-' . $date . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Update order status
     */
    public function updateStatus(string $status): bool
    {
        $validStatuses = ['Pending', 'Processing', 'Delivered', 'Cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $this->status = $status;
        return $this->save();
    }

    /**
     * Calculate total profit for the order
     */
    public function calculateTotalProfit(): float
    {
        return $this->orderProducts->sum('profit');
    }

    /**
     * Get profit percentage
     */
    public function getProfitPercentageAttribute(): ?float
    {
        if ($this->sub_total == 0) {
            return 0;
        }
        
        $profit = $this->calculateTotalProfit();
        return ($profit / $this->sub_total) * 100;
    }
}