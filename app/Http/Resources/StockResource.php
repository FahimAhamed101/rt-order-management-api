<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? null,
            'product_barcode' => $this->product->barcode ?? null,
            'sku' => $this->sku,
            'sale_price' => (float) $this->sale_price,
            'purchase_price' => (float) $this->purchase_price,
            'quantity' => $this->quantity,
            'total_value' => (float) ($this->purchase_price * $this->quantity),
            'total_sale_value' => (float) ($this->sale_price * $this->quantity),
            'profit_per_unit' => (float) ($this->sale_price - $this->purchase_price),
            'total_profit_potential' => (float) (($this->sale_price - $this->purchase_price) * $this->quantity),
            'profit_percentage' => $this->profit_percentage,
            'status' => $this->getStockStatus(),
            'last_updated_at' => $this->last_updated_at,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Relationships (only when loaded)
            'product' => new ProductResource($this->whenLoaded('product')),
            'stock_logs' => StockLogResource::collection($this->whenLoaded('stockLogs')),
            
            // Additional calculated fields
            'age_in_days' => $this->created_at->diffInDays(now()),
            'is_low_stock' => $this->quantity > 0 && $this->quantity <= 10,
            'is_out_of_stock' => $this->quantity <= 0,
            'is_available' => $this->quantity > 0,
        ];
    }
    
    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => 'v1',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}