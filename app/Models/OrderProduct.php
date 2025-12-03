<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'stock_id',
        'quantity',
        'sale_price',
        'purchase_price', // Add this
        'sub_total',
        'profit'
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'purchase_price' => 'decimal:2', // Add this
        'sub_total' => 'decimal:2',
        'profit' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * Get the order that owns the order product.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product that owns the order product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the stock that owns the order product.
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Calculate profit based on purchase price and sale price
     */
    public static function calculateProfit(float $salePrice, float $purchasePrice, int $quantity): float
    {
        $profitPerUnit = $salePrice - $purchasePrice;
        return $profitPerUnit * $quantity;
    }

    /**
     * Get profit percentage
     */
    public function getProfitPercentageAttribute(): float
    {
        if ($this->purchase_price == 0) {
            return 0;
        }
        
        $profitPerUnit = $this->sale_price - $this->purchase_price;
        return ($profitPerUnit / $this->purchase_price) * 100;
    }

    /**
     * Get profit per unit
     */
    public function getProfitPerUnitAttribute(): float
    {
        return $this->sale_price - $this->purchase_price;
    }
}