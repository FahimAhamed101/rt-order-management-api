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
        'total_amount',
        'customer_name',
        'status'
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

  
    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

 
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->invoice_number)) {
                $order->invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }

 
    public function updateStatus(string $status): bool
    {
        $validStatuses = ['Pending', 'Processing', 'Delivered', 'Cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $this->status = $status;
        return $this->save();
    }


    public function calculateTotalProfit(): float
    {
        return $this->orderProducts->sum('profit');
    }
}