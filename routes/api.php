<?php

use Illuminate\Http\Request;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;

use App\Http\Controllers\API\ProductSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });
    

    Route::get('public/products/search', [ProductSearchController::class, 'searchForSale']);
});


Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    

    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });
    
 
    Route::prefix('products')->group(function () {
        Route::get('search-for-sale', [ProductSearchController::class, 'searchForSale']);
        Route::get('{id}/fifo-stock', [ProductSearchController::class, 'getFIFOStock']);
        Route::get('barcode/{barcode}', [ProductSearchController::class, 'searchByBarcode']);
    });
    

    

    Route::apiResource('products', ProductController::class)->except(['index']);
    Route::get('products', [ProductController::class, 'index']);
    

   
});