<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
   
    public function index(Request $request)
    {
        try {
            $query = Product::query();
            
           
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            }
          
            if ($request->has('barcode')) {
                $query->where('barcode', $request->barcode);
            }
            
          
            $orderBy = $request->get('order_by', 'created_at');
            $orderDirection = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDirection);
            
       
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'barcode' => 'required|string|unique:products,barcode',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::create([
                'name' => $request->name,
                'barcode' => $request->barcode,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 
    public function show($id)
    {
        try {
            $product = Product::with(['stocks' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

  
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'barcode' => 'sometimes|required|string|unique:products,barcode,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
                $updateData['slug'] = Str::slug($request->name);
            }
            if ($request->has('barcode')) {
                $updateData['barcode'] = $request->barcode;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }

            $product->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            
   
            if ($product->stocks()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete product with existing stocks'
                ], 400);
            }
            
       
            if ($product->orderProducts()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete product with order history'
                ], 400);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getByBarcode($barcode)
    {
        try {
            $product = Product::with(['stocks' => function($query) {
                $query->where('quantity', '>', 0)
                      ->orderBy('created_at', 'desc');
            }])->where('barcode', $barcode)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }


    public function getBySlug($slug)
    {
        try {
            $product = Product::with(['stocks'])->where('slug', $slug)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}