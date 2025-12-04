<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductSearchController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
    
    // Public product search
    Route::get('public/products/search', [ProductSearchController::class, 'searchForSale']);
});

// Protected routes
Route::middleware('auth:api')->prefix('v1')->group(function () {
    
    // Auth management
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });
    
    // Product Search for Sale (with FIFO)
    Route::prefix('products')->group(function () {
        Route::get('search-for-sale', [ProductSearchController::class, 'searchForSale']);
        Route::get('{id}/fifo-stock', [ProductSearchController::class, 'getFIFOStock']);
        Route::get('barcode/{barcode}', [ProductSearchController::class, 'searchByBarcode']);
    });
    
    // Order Management Routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::put('{id}', [OrderController::class, 'update']);
        Route::delete('{id}', [OrderController::class, 'destroy']);
        Route::post('{id}/fake-payment', [OrderController::class, 'fakePayment']);
        Route::put('{id}/status', [OrderController::class, 'updateStatus']);
        Route::get('invoice/{invoiceNumber}', [OrderController::class, 'getByInvoice']);
        Route::get('statistics', [OrderController::class, 'getStatistics']);
        Route::get('daily-sales', [OrderController::class, 'getDailySales']);
    });
    
    // Product CRUD Routes
    Route::apiResource('products', ProductController::class)->except(['index']);
    Route::get('products', [ProductController::class, 'index']);
    
    // Stock CRUD Routes
    Route::apiResource('stocks', StockController::class);
    Route::post('stocks/{id}/quantity', [StockController::class, 'updateQuantity']);
    Route::get('stocks/low-stock', [StockController::class, 'getLowStock']);
    Route::get('stocks/summary', [StockController::class, 'getStockSummary']);
    Route::get('stocks/sku/{sku}', [StockController::class, 'getBySku']);
    Route::get('stocks/{productId}/history', [StockController::class, 'getStockHistory']);
});