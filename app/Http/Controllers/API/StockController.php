<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
 
    public function index(Request $request)
    {
        try {
            $query = Stock::with('product');
            
      
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }
            
         
            if ($request->has('sku')) {
                $query->where('sku', 'like', "%{$request->sku}%");
            }
            

            if ($request->has('in_stock') && $request->in_stock == 'true') {
                $query->where('quantity', '>', 0);
            }
            
         
            if ($request->has('low_stock') && $request->low_stock == 'true') {
                $query->where('quantity', '>', 0)->where('quantity', '<=', 10);
            }
            
      
            if ($request->has('out_of_stock') && $request->out_of_stock == 'true') {
                $query->where('quantity', '<=', 0);
            }
            
     
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->whereHas('product', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            }
            
        
            $orderBy = $request->get('order_by', 'created_at');
            $orderDirection = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDirection);
            
       
            $perPage = $request->get('per_page', 15);
            $stocks = $query->paginate($perPage);
            
      
            $stocks->getCollection()->transform(function ($stock) {
                $stock->total_value = $stock->quantity * $stock->purchase_price;
                $stock->total_sale_value = $stock->quantity * $stock->sale_price;
                $stock->potential_profit = $stock->quantity * ($stock->sale_price - $stock->purchase_price);
                $stock->profit_percentage = $stock->purchase_price > 0 ? 
                    (($stock->sale_price - $stock->purchase_price) / $stock->purchase_price) * 100 : 0;
                $stock->is_low_stock = $stock->quantity > 0 && $stock->quantity <= 10;
                $stock->is_out_of_stock = $stock->quantity <= 0;
                $stock->is_available = $stock->quantity > 0;
                return $stock;
            });

            return response()->json([
                'success' => true,
                'message' => 'Stocks retrieved successfully',
                'data' => $stocks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'sku' => 'required|string|unique:stocks,sku',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $stock = Stock::create([
                'product_id' => $request->product_id,
                'sku' => $request->sku,
                'sale_price' => $request->sale_price,
                'purchase_price' => $request->purchase_price,
                'quantity' => $request->quantity,
                'last_updated_at' => now(),
            ]);

           
            StockLog::create([
                'type' => 'stock-adjustment',
                'stock_id' => $stock->id,
                'product_id' => $stock->product_id,
                'previous_quantity' => 0,
                'change_quantity' => $stock->quantity,
                'current_quantity' => $stock->quantity,
                'user_id' => auth()->id(),
                'remarks' => 'Initial stock creation',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock created successfully',
                'data' => $stock->load('product')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function show($id)
    {
        try {
            $stock = Stock::with(['product', 'stockLogs' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }])->findOrFail($id);

            // Add calculated fields
            $stock->total_value = $stock->quantity * $stock->purchase_price;
            $stock->total_sale_value = $stock->quantity * $stock->sale_price;
            $stock->potential_profit = $stock->quantity * ($stock->sale_price - $stock->purchase_price);
            $stock->profit_percentage = $stock->purchase_price > 0 ? 
                (($stock->sale_price - $stock->purchase_price) / $stock->purchase_price) * 100 : 0;

            return response()->json([
                'success' => true,
                'message' => 'Stock retrieved successfully',
                'data' => $stock
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

  
    public function update(Request $request, $id)
    {
        $stock = Stock::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'sku' => 'sometimes|required|string|unique:stocks,sku,' . $id,
            'sale_price' => 'sometimes|required|numeric|min:0',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'quantity' => 'sometimes|required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only(['sku', 'sale_price', 'purchase_price']);
            

            if ($request->has('quantity')) {
                $oldQuantity = $stock->quantity;
                $newQuantity = $request->quantity;
                $changeQuantity = $newQuantity - $oldQuantity;
                
   
                if ($changeQuantity != 0) {
                    StockLog::create([
                        'type' => 'stock-adjustment',
                        'stock_id' => $stock->id,
                        'product_id' => $stock->product_id,
                        'previous_quantity' => $oldQuantity,
                        'change_quantity' => $changeQuantity,
                        'current_quantity' => $newQuantity,
                        'user_id' => auth()->id(),
                        'remarks' => 'Manual stock adjustment',
                    ]);
                }
                
                $updateData['quantity'] = $newQuantity;
            }
            
            $updateData['last_updated_at'] = now();
            
            $stock->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => $stock
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $stock = Stock::findOrFail($id);
            
      
            if ($stock->orderProducts()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete stock with order history'
                ], 400);
            }

       
            StockLog::create([
                'type' => 'stock-adjustment',
                'stock_id' => $stock->id,
                'product_id' => $stock->product_id,
                'previous_quantity' => $stock->quantity,
                'change_quantity' => -$stock->quantity,
                'current_quantity' => 0,
                'user_id' => auth()->id(),
                'remarks' => 'Stock deletion',
            ]);

            $stock->delete();

            return response()->json([
                'success' => true,
                'message' => 'Stock deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function updateQuantity(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'operation' => 'required|in:add,subtract,set',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $stock = Stock::findOrFail($id);
            $oldQuantity = $stock->quantity;
            $changeQuantity = 0;
            $newQuantity = $oldQuantity;
            
            switch ($request->operation) {
                case 'add':
                    $changeQuantity = $request->quantity;
                    $newQuantity = $oldQuantity + $changeQuantity;
                    break;
                case 'subtract':
                    $changeQuantity = -$request->quantity;
                    $newQuantity = $oldQuantity - $request->quantity;
                    
                    if ($newQuantity < 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient stock quantity. Available: ' . $oldQuantity
                        ], 400);
                    }
                    break;
                case 'set':
                    $newQuantity = $request->quantity;
                    $changeQuantity = $newQuantity - $oldQuantity;
                    
                    if ($newQuantity < 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Quantity cannot be negative'
                        ], 400);
                    }
                    break;
            }
            
       
            if ($changeQuantity != 0) {
                StockLog::create([
                    'type' => 'stock-adjustment',
                    'stock_id' => $stock->id,
                    'product_id' => $stock->product_id,
                    'previous_quantity' => $oldQuantity,
                    'change_quantity' => $changeQuantity,
                    'current_quantity' => $newQuantity,
                    'user_id' => auth()->id(),
                    'remarks' => $request->remarks ?? 'Stock quantity adjustment: ' . $request->operation,
                ]);
            }
            
    
            $stock->quantity = $newQuantity;
            $stock->last_updated_at = now();
            $stock->save();

            return response()->json([
                'success' => true,
                'message' => 'Stock quantity updated successfully',
                'data' => $stock
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock quantity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function getBySku($sku)
    {
        try {
            $stock = Stock::with('product')
                ->where('sku', $sku)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Stock retrieved successfully',
                'data' => $stock
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

 
    public function getLowStock(Request $request)
    {
        try {
            $threshold = $request->get('threshold', 10);
            
            $lowStocks = Stock::with('product')
                ->where('quantity', '>', 0)
                ->where('quantity', '<=', $threshold)
                ->orderBy('quantity', 'asc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Low stock items retrieved',
                'data' => $lowStocks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve low stock items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function getStockSummary()
    {
        try {
            $totalStocks = Stock::count();
            $totalQuantity = Stock::sum('quantity');
            $totalPurchaseValue = Stock::sum(\DB::raw('quantity * purchase_price'));
            $totalSaleValue = Stock::sum(\DB::raw('quantity * sale_price'));
            $inStockItems = Stock::where('quantity', '>', 0)->count();
            $outOfStockItems = Stock::where('quantity', '<=', 0)->count();
            
            $lowStockItems = Stock::where('quantity', '>', 0)
                ->where('quantity', '<=', 10)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Stock summary retrieved',
                'data' => [
                    'total_stocks' => $totalStocks,
                    'total_quantity' => $totalQuantity,
                    'total_purchase_value' => (float) $totalPurchaseValue,
                    'total_sale_value' => (float) $totalSaleValue,
                    'total_profit_potential' => (float) ($totalSaleValue - $totalPurchaseValue),
                    'in_stock_items' => $inStockItems,
                    'out_of_stock_items' => $outOfStockItems,
                    'low_stock_items' => $lowStockItems,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  
    public function getStockHistory($productId)
    {
        try {
            $product = Product::findOrFail($productId);
            
            $stocks = Stock::where('product_id', $productId)
                ->with(['stockLogs' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Stock history retrieved',
                'data' => [
                    'product' => $product,
                    'stocks' => $stocks,
                    'total_quantity' => $stocks->sum('quantity')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}