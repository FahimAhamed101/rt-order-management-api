<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductSearchController extends Controller
{
    
    public function searchForSale(Request $request)
    {
        try {
            $searchTerm = $request->get('search', '');
            $barcode = $request->get('barcode', '');
            
          
            $query = Product::select([
                'products.id',
                'products.name',
                'products.barcode',
                'products.slug',
                'products.description',
                DB::raw('SUM(stocks.quantity) as total_quantity')
            ])
            ->join('stocks', 'products.id', '=', 'stocks.product_id')
            ->where('stocks.quantity', '>', 0)
            ->groupBy('products.id', 'products.name', 'products.barcode', 'products.slug', 'products.description');
            
     
            if (!empty($searchTerm)) {
                $query->where(function($q) use ($searchTerm) {
                    $q->where('products.name', 'like', "%{$searchTerm}%")
                      ->orWhere('products.barcode', 'like', "%{$searchTerm}%");
                });
            }
            
        
            if (!empty($barcode)) {
                $query->where('products.barcode', $barcode);
            }
            
            $products = $query->paginate(20);
            
    
            $products->getCollection()->transform(function ($product) {
             
                $fifoStock = Stock::where('product_id', $product->id)
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
        
                $allStocks = Stock::where('product_id', $product->id)
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();
                
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'total_quantity' => $product->total_quantity,
                    'fifo_stock' => $fifoStock ? [
                        'id' => $fifoStock->id,
                        'sku' => $fifoStock->sku,
                        'sale_price' => $fifoStock->sale_price,
                        'quantity' => $fifoStock->quantity,
                        'created_at' => $fifoStock->created_at,
                        'profit_percentage' => $fifoStock->profit_percentage,
                    ] : null,
                    'all_stocks' => $allStocks->map(function ($stock) {
                        return [
                            'id' => $stock->id,
                            'sku' => $stock->sku,
                            'sale_price' => $stock->sale_price,
                            'quantity' => $stock->quantity,
                            'created_at' => $stock->created_at,
                            'age_days' => now()->diffInDays($stock->created_at),
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search products',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getFIFOStock($productId)
    {
        try {
            $product = Product::findOrFail($productId);
            
          
            $stocks = Stock::where('product_id', $productId)
                ->where('quantity', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();
            
            if ($stocks->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available stock for this product'
                ], 404);
            }
            
            $totalQuantity = $stocks->sum('quantity');
            $fifoStock = $stocks->first();
            
            return response()->json([
                'success' => true,
                'message' => 'FIFO stock details retrieved',
                'data' => [
                    'product' => $product,
                    'fifo_stock' => $fifoStock,
                    'total_available_quantity' => $totalQuantity,
                    'all_stocks' => $stocks
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get FIFO stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}