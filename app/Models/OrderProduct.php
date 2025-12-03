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
        'sale_price',
        'sub_total',
        'profit',
        'quantity'
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

  
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }


    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public static function calculateProfit(float $salePrice, float $purchasePrice, int $quantity): float
    {
        $profitPerUnit = $salePrice - $purchasePrice;
        return $profitPerUnit * $quantity;
    }


    public static function createWithProfit(array $data): self
    {
        $stock = Stock::findOrFail($data['stock_id']);
        
        $data['sale_price'] = $stock->sale_price;
        $data['sub_total'] = $stock->sale_price * $data['quantity'];
        $data['profit'] = self::calculateProfit(
            $stock->sale_price,
            $stock->purchase_price,
            $data['quantity']
        );

        return self::create($data);
    }
}