<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
  
    public function index(Request $request)
    {
        try {
            $query = Order::with(['orderProducts.product', 'orderProducts.stock'])
                ->orderBy('created_at', 'desc');
            
            
            if ($request->has('customer_name') && !empty($request->customer_name)) {
                $query->where('customer_name', 'like', "%{$request->customer_name}%");
            }
            
        
            if ($request->has('invoice_number') && !empty($request->invoice_number)) {
                $query->where('invoice_number', 'like', "%{$request->invoice_number}%");
            }
            
          
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }
            
  
            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->whereDate('date_time', '>=', $request->start_date);
            }
            
            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->whereDate('date_time', '<=', $request->end_date);
            }
            
    
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%");
                });
            }
            
      
            $perPage = $request->get('per_page', 15);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.stock_id' => 'required|exists:stocks,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'nullable|in:cash,card,online,bkash,nagad,rocket',
            'payment_status' => 'nullable|in:pending,paid,partial',
            'paid_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_charge' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $totalAmount = 0;
            $totalProfit = 0;
            $orderProducts = [];
            
     
            foreach ($request->products as $productItem) {
                $stock = Stock::with('product')->findOrFail($productItem['stock_id']);
                
    
                if ($stock->quantity < $productItem['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$stock->product->name}. Available: {$stock->quantity}, Requested: {$productItem['quantity']}");
                }
                
           
                $subTotal = $stock->sale_price * $productItem['quantity'];
                $profit = ($stock->sale_price - $stock->purchase_price) * $productItem['quantity'];
                
                $totalAmount += $subTotal;
                $totalProfit += $profit;
                
                $orderProducts[] = [
                    'stock_id' => $productItem['stock_id'],
                    'product_id' => $stock->product_id,
                    'quantity' => $productItem['quantity'],
                    'sale_price' => $stock->sale_price,
                    'purchase_price' => $stock->purchase_price,
                    'sub_total' => $subTotal,
                    'profit' => $profit,
                ];
            }
            
     
            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;
            $shippingCharge = $request->shipping_charge ?? 0;
            
            $finalAmount = $totalAmount - $discount + $tax + $shippingCharge;
            $paidAmount = $request->paid_amount ?? 0;
            $dueAmount = max(0, $finalAmount - $paidAmount);
            
     
            $orderData = [
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email,
                'customer_address' => $request->customer_address,
                'total_amount' => $finalAmount,
                'sub_total' => $totalAmount,
                'discount' => $discount,
                'tax' => $tax,
                'shipping_charge' => $shippingCharge,
                'payment_method' => $request->payment_method ?? 'cash',
                'payment_status' => $request->payment_status ?? 'pending',
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'status' => 'Pending',
                'date_time' => now(),
                'notes' => $request->notes,
            ];
            
            $order = Order::create($orderData);
            
          
            foreach ($orderProducts as $orderProductData) {
                $orderProductData['order_id'] = $order->id;
                
        
                $orderProduct = OrderProduct::create($orderProductData);
                
         
                $stock = Stock::find($orderProductData['stock_id']);
                if ($stock) {
         
                    StockLog::create([
                        'type' => 'order-create',
                        'stock_id' => $stock->id,
                        'product_id' => $stock->product_id,
                        'previous_quantity' => $stock->quantity,
                        'change_quantity' => -$orderProductData['quantity'],
                        'current_quantity' => $stock->quantity - $orderProductData['quantity'],
                        'order_id' => $order->id,
                        'user_id' => auth()->id(),
                        'remarks' => 'Order creation - ' . $order->invoice_number,
                    ]);
                    
      
                    $stock->quantity -= $orderProductData['quantity'];
                    $stock->last_updated_at = now();
                    $stock->save();
                }
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['orderProducts.product', 'orderProducts.stock'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   
    public function show($id)
    {
        try {
            $order = Order::with(['orderProducts.product', 'orderProducts.stock'])
                ->findOrFail($id);

 
            $totalProfit = $order->orderProducts->sum('profit');
            $totalProducts = $order->orderProducts->sum('quantity');

            $orderData = $order->toArray();
            $orderData['total_profit'] = $totalProfit;
            $orderData['total_products'] = $totalProducts;
            $orderData['profit_percentage'] = $order->sub_total > 0 ? ($totalProfit / $order->sub_total) * 100 : 0;

            return response()->json([
                'success' => true,
                'message' => 'Order retrieved successfully',
                'data' => $orderData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

 
    public function update(Request $request, $id)
    {
        $order = Order::with('orderProducts')->findOrFail($id);
        
  
        if ($order->status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be edited'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'products' => 'sometimes|required|array|min:1',
            'products.*.stock_id' => 'required|exists:stocks,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'nullable|in:cash,card,online,bkash,nagad,rocket',
            'payment_status' => 'nullable|in:pending,paid,partial',
            'paid_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping_charge' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
         
            foreach ($order->orderProducts as $oldOrderProduct) {
                $stock = Stock::find($oldOrderProduct->stock_id);
                if ($stock) {
       
                    StockLog::create([
                        'type' => 'order-update',
                        'stock_id' => $stock->id,
                        'product_id' => $stock->product_id,
                        'previous_quantity' => $stock->quantity,
                        'change_quantity' => $oldOrderProduct->quantity,
                        'current_quantity' => $stock->quantity + $oldOrderProduct->quantity,
                        'order_id' => $order->id,
                        'user_id' => auth()->id(),
                        'remarks' => 'Order update - Restoring stock from old order',
                    ]);
                    
            
                    $stock->quantity += $oldOrderProduct->quantity;
                    $stock->last_updated_at = now();
                    $stock->save();
                }
            }
            
       
            $order->orderProducts()->delete();
            
     
            $totalAmount = 0;
            $totalProfit = 0;
            $orderProducts = [];
            
            if ($request->has('products')) {
                foreach ($request->products as $productItem) {
                    $stock = Stock::with('product')->findOrFail($productItem['stock_id']);
                    
              
                    if ($stock->quantity < $productItem['quantity']) {
                        throw new \Exception("Insufficient stock for product: {$stock->product->name}. Available: {$stock->quantity}, Requested: {$productItem['quantity']}");
                    }
                    
                 
                    $subTotal = $stock->sale_price * $productItem['quantity'];
                    $profit = ($stock->sale_price - $stock->purchase_price) * $productItem['quantity'];
                    
                    $totalAmount += $subTotal;
                    $totalProfit += $profit;
                    
                    $orderProducts[] = [
                        'stock_id' => $productItem['stock_id'],
                        'product_id' => $stock->product_id,
                        'quantity' => $productItem['quantity'],
                        'sale_price' => $stock->sale_price,
                        'purchase_price' => $stock->purchase_price,
                        'sub_total' => $subTotal,
                        'profit' => $profit,
                    ];
                }
                
     
                $discount = $request->discount ?? $order->discount;
                $tax = $request->tax ?? $order->tax;
                $shippingCharge = $request->shipping_charge ?? $order->shipping_charge;
                
                $finalAmount = $totalAmount - $discount + $tax + $shippingCharge;
                
      
                $order->update([
                    'total_amount' => $finalAmount,
                    'sub_total' => $totalAmount,
                    'discount' => $discount,
                    'tax' => $tax,
                    'shipping_charge' => $shippingCharge,
                ]);
            }
            
       
            $updateData = [];
            if ($request->has('customer_name')) $updateData['customer_name'] = $request->customer_name;
            if ($request->has('customer_phone')) $updateData['customer_phone'] = $request->customer_phone;
            if ($request->has('customer_email')) $updateData['customer_email'] = $request->customer_email;
            if ($request->has('customer_address')) $updateData['customer_address'] = $request->customer_address;
            if ($request->has('payment_method')) $updateData['payment_method'] = $request->payment_method;
            if ($request->has('payment_status')) $updateData['payment_status'] = $request->payment_status;
            if ($request->has('notes')) $updateData['notes'] = $request->notes;
            
            if ($request->has('paid_amount')) {
                $paidAmount = $request->paid_amount;
                $currentTotal = $request->has('products') ? $finalAmount : $order->total_amount;
                
                if ($paidAmount > $currentTotal) {
                    throw new \Exception("Paid amount cannot be greater than total amount");
                }
                
                $updateData['paid_amount'] = $paidAmount;
                $updateData['due_amount'] = $currentTotal - $paidAmount;
                $updateData['payment_status'] = $paidAmount == $currentTotal ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
            }
            
            if (!empty($updateData)) {
                $order->update($updateData);
            }
            
       
            if ($request->has('products')) {
                foreach ($orderProducts as $orderProductData) {
                    $orderProductData['order_id'] = $order->id;
                    
         
                    $orderProduct = OrderProduct::create($orderProductData);
                    
                
                    $stock = Stock::find($orderProductData['stock_id']);
                    if ($stock) {
                     
                        StockLog::create([
                            'type' => 'order-update',
                            'stock_id' => $stock->id,
                            'product_id' => $stock->product_id,
                            'previous_quantity' => $stock->quantity,
                            'change_quantity' => -$orderProductData['quantity'],
                            'current_quantity' => $stock->quantity - $orderProductData['quantity'],
                            'order_id' => $order->id,
                            'user_id' => auth()->id(),
                            'remarks' => 'Order update - Adding new products',
                        ]);
                        
                     
                        $stock->quantity -= $orderProductData['quantity'];
                        $stock->last_updated_at = now();
                        $stock->save();
                    }
                }
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $order->load(['orderProducts.product', 'orderProducts.stock'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  
    public function destroy($id)
    {
        DB::beginTransaction();
        
        try {
            $order = Order::with('orderProducts')->findOrFail($id);
            
        
            if ($order->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be deleted'
                ], 400);
            }
            
         
            foreach ($order->orderProducts as $orderProduct) {
                $stock = Stock::find($orderProduct->stock_id);
                if ($stock) {
                  
                    StockLog::create([
                        'type' => 'order-delete',
                        'stock_id' => $stock->id,
                        'product_id' => $stock->product_id,
                        'previous_quantity' => $stock->quantity,
                        'change_quantity' => $orderProduct->quantity,
                        'current_quantity' => $stock->quantity + $orderProduct->quantity,
                        'order_id' => $order->id,
                        'user_id' => auth()->id(),
                        'remarks' => 'Order deletion - Restoring stock',
                    ]);
                    
                
                    $stock->quantity += $orderProduct->quantity;
                    $stock->last_updated_at = now();
                    $stock->save();
                }
            }
            
         
            $order->orderProducts()->delete();
            
         
            $order->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function fakePayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:cash,card,online,bkash,nagad,rocket',
            'paid_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);
            
            $paidAmount = $request->paid_amount;
            $totalAmount = $order->total_amount;
            
            if ($paidAmount > $totalAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paid amount cannot be greater than total amount'
                ], 400);
            }
            
            $paymentStatus = $paidAmount == $totalAmount ? 'paid' : 'partial';
            $dueAmount = $totalAmount - $paidAmount;
            
            $order->update([
                'payment_method' => $request->payment_method,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $paymentStatus,
                'payment_date' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'invoice_number' => $order->invoice_number,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'payment_status' => $paymentStatus,
                    'payment_method' => $request->payment_method,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function getByInvoice($invoiceNumber)
    {
        try {
            $order = Order::with(['orderProducts.product', 'orderProducts.stock'])
                ->where('invoice_number', $invoiceNumber)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Order retrieved successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

 
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Pending,Processing,Delivered,Cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $order = Order::with('orderProducts')->findOrFail($id);
            $oldStatus = $order->status;
            $newStatus = $request->status;
            
        
            if ($newStatus === 'Cancelled' && $oldStatus !== 'Cancelled') {
                foreach ($order->orderProducts as $orderProduct) {
                    $stock = Stock::find($orderProduct->stock_id);
                    if ($stock) {
                    
                        StockLog::create([
                            'type' => 'order-update',
                            'stock_id' => $stock->id,
                            'product_id' => $stock->product_id,
                            'previous_quantity' => $stock->quantity,
                            'change_quantity' => $orderProduct->quantity,
                            'current_quantity' => $stock->quantity + $orderProduct->quantity,
                            'order_id' => $order->id,
                            'user_id' => auth()->id(),
                            'remarks' => 'Order cancelled - Restoring stock',
                        ]);
                        
                 
                        $stock->quantity += $orderProduct->quantity;
                        $stock->last_updated_at = now();
                        $stock->save();
                    }
                }
            }
            
       
            if ($oldStatus === 'Cancelled' && $newStatus !== 'Cancelled') {
                foreach ($order->orderProducts as $orderProduct) {
                    $stock = Stock::find($orderProduct->stock_id);
                    if ($stock) {
                        if ($stock->quantity < $orderProduct->quantity) {
                            throw new \Exception("Insufficient stock to uncancel order for product: {$stock->product->name}");
                        }
                        
              
                        StockLog::create([
                            'type' => 'order-update',
                            'stock_id' => $stock->id,
                            'product_id' => $stock->product_id,
                            'previous_quantity' => $stock->quantity,
                            'change_quantity' => -$orderProduct->quantity,
                            'current_quantity' => $stock->quantity - $orderProduct->quantity,
                            'order_id' => $order->id,
                            'user_id' => auth()->id(),
                            'remarks' => 'Order uncancelled - Deducting stock',
                        ]);
                        
                    
                        $stock->quantity -= $orderProduct->quantity;
                        $stock->last_updated_at = now();
                        $stock->save();
                    }
                }
            }
            
            $order->update(['status' => $newStatus]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
  
    public function getStatistics(Request $request)
    {
        try {
         
            $startDate = $request->get('start_date', now()->subMonth());
            $endDate = $request->get('end_date', now());
            
     
            $totalOrders = Order::whereBetween('date_time', [$startDate, $endDate])->count();
            
       
            $totalRevenue = Order::whereBetween('date_time', [$startDate, $endDate])
                ->where('status', '!=', 'Cancelled')
                ->sum('total_amount');
            
       
            $totalProfit = OrderProduct::whereHas('order', function($query) use ($startDate, $endDate) {
                    $query->whereBetween('date_time', [$startDate, $endDate])
                          ->where('status', '!=', 'Cancelled');
                })
                ->sum('profit');
            
      
            $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
                ->whereBetween('date_time', [$startDate, $endDate])
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');
            
     
            $topProducts = OrderProduct::select('product_id', 
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('SUM(sub_total) as total_sales'),
                    DB::raw('SUM(profit) as total_profit')
                )
                ->whereHas('order', function($query) use ($startDate, $endDate) {
                    $query->whereBetween('date_time', [$startDate, $endDate])
                          ->where('status', '!=', 'Cancelled');
                })
                ->with('product')
                ->groupBy('product_id')
                ->orderBy('total_quantity', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Order statistics retrieved',
                'data' => [
                    'total_orders' => $totalOrders,
                    'total_revenue' => (float) $totalRevenue,
                    'total_profit' => (float) $totalProfit,
                    'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
                    'orders_by_status' => $ordersByStatus,
                    'top_products' => $topProducts,
                    'date_range' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getDailySales(Request $request)
    {
        try {
            $days = $request->get('days', 30);
            
            $dailySales = Order::select(
                    DB::raw('DATE(date_time) as date'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(total_amount) as total_sales'),
                    DB::raw('SUM(CASE WHEN status = "Delivered" THEN total_amount ELSE 0 END) as delivered_sales')
                )
                ->where('date_time', '>=', now()->subDays($days))
                ->where('status', '!=', 'Cancelled')
                ->groupBy(DB::raw('DATE(date_time)'))
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daily sales report retrieved',
                'data' => $dailySales
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily sales report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}