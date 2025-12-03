<?php

use Illuminate\Http\Request;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductSearchController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\OrderController;


use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->name('login'); // Named route
    });
    
    Route::get('public/products/search', [ProductSearchController::class, 'searchForSale']);
    
    
    Route::middleware(['auth:api'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('user', [AuthController::class, 'user']);
        });
        
        Route::prefix('products')->group(function () {
            Route::get('search-for-sale', [ProductSearchController::class, 'searchForSale']);
            Route::get('{id}/fifo-stock', [ProductSearchController::class, 'getFIFOStock']);
            Route::get('barcode/{barcode}', [ProductSearchController::class, 'searchByBarcode']);
        });
        
  
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::put('{id}', [OrderController::class, 'update']);
        Route::delete('{id}', [OrderController::class, 'destroy']);
        Route::post('{id}/fake-payment', [OrderController::class, 'fakePayment']);
        Route::put('{id}/status', [OrderController::class, 'updateStatus']);
        Route::get('invoice/{invoiceNumber}', [OrderController::class, 'getByInvoice']);
    });
    
 
    Route::apiResource('products', ProductController::class)->except(['index']);
    Route::get('products', [ProductController::class, 'index']);
    

    Route::apiResource('stocks', StockController::class);
    });
});
