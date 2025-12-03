<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'stock_id',
        'product_id',
        'previous_quantity',
        'change_quantity',
        'current_quantity',
        'remarks',
        'order_id',
        'user_id'
    ];

    protected $casts = [
        'previous_quantity' => 'integer',
        'change_quantity' => 'integer',
        'current_quantity' => 'integer',
    ];

    /**
     * Get the stock that owns the log.
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * Get the product that owns the log.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the order associated with the log.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create stock log
     */
    public static function createLog(
        string $type,
        Stock $stock,
        int $changeQuantity,
        ?int $orderId = null,
        ?string $remarks = null
    ): self {
        return self::create([
            'type' => $type,
            'stock_id' => $stock->id,
            'product_id' => $stock->product_id,
            'previous_quantity' => $stock->quantity,
            'change_quantity' => $changeQuantity,
            'current_quantity' => $stock->quantity + $changeQuantity,
            'remarks' => $remarks,
            'order_id' => $orderId,
            'user_id' => auth()->id(),
        ]);
    }
}