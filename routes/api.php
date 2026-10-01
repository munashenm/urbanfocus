<?php

use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\StoreSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->prefix('store')->group(function () {
    Route::get('/health', [StoreSyncController::class, 'health']);
    Route::get('/catalogue', [StoreSyncController::class, 'catalogue']);
    Route::get('/lookup', [StoreSyncController::class, 'lookup']);
    Route::get('/products', [StoreSyncController::class, 'findProduct']);
    Route::post('/products', [StoreSyncController::class, 'createProduct']);
    Route::patch('/products/{sku}/price', [StoreSyncController::class, 'updatePrice'])->where('sku', '[^/]+');
    Route::patch('/products/{sku}/stock', [StoreSyncController::class, 'updateStock'])->where('sku', '[^/]+');
    Route::patch('/products/{sku}/content', [StoreSyncController::class, 'updateContent'])->where('sku', '[^/]+');
    Route::patch('/products/{sku}/images', [StoreSyncController::class, 'updateImages'])->where('sku', '[^/]+');
    Route::patch('/products/{sku}/publication', [StoreSyncController::class, 'updatePublication'])->where('sku', '[^/]+');
    Route::get('/orders', [StoreSyncController::class, 'orders']);
});

Route::middleware('api.key')->group(function () {
    Route::get('/products', [ApiProductController::class, 'index']);
    Route::get('/products/{identifier}', [ApiProductController::class, 'show']);

    // Push product & blog data (with AI captions) to the Make.com webhooks.
    Route::prefix('marketing')->group(function () {
        Route::get('/products/{identifier}/preview', [MarketingController::class, 'previewProduct']);
        Route::post('/products/{identifier}/dispatch', [MarketingController::class, 'dispatchProduct']);
        Route::get('/articles/{identifier}/preview', [MarketingController::class, 'previewArticle']);
        Route::post('/articles/{identifier}/dispatch', [MarketingController::class, 'dispatchArticle']);
    });
});
