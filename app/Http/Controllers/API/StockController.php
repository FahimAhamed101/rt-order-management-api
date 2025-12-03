<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Resources\StockResource;
class StockController extends Controller
{

   public function index(Request $request)
{
    $products = Product::paginate();
    return StockResource::collection($products);
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

            return response()->json([
                'success' => true,
                'message' => 'Stock created successfully',
                'data' => $stock
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
            $stock = Stock::with('product')->findOrFail($id);

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
            $updateData = $request->only(['sku', 'sale_price', 'purchase_price', 'quantity']);
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
            
            switch ($request->operation) {
                case 'add':
                    $stock->updateQuantity($request->quantity, true);
                    break;
                case 'subtract':
                    if ($stock->quantity < $request->quantity) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient stock quantity'
                        ], 400);
                    }
                    $stock->updateQuantity($request->quantity, false);
                    break;
                case 'set':
                    if ($request->quantity < 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Quantity cannot be negative'
                        ], 400);
                    }
                    $stock->quantity = $request->quantity;
                    $stock->last_updated_at = now();
                    $stock->save();
                    break;
            }

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
            $totalValue = Stock::sum(\DB::raw('quantity * purchase_price'));
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
                    'total_value' => (float) $totalValue,
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
}