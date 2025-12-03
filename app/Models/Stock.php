<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'sale_price',
        'purchase_price',
        'quantity',
        'last_updated_at'
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'last_updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    
    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

  
    public function getProfitPercentageAttribute(): float
    {
        if ($this->purchase_price == 0) {
            return 0;
        }
        
        return (($this->sale_price - $this->purchase_price) / $this->purchase_price) * 100;
    }

 
    public function updateQuantity(int $quantity, bool $increment = true): void
    {
        if ($increment) {
            $this->increment('quantity', $quantity);
        } else {
            $this->decrement('quantity', $quantity);
        }
        
        $this->last_updated_at = now();
        $this->save();
    }
}