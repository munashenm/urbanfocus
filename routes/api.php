<?php

use App\Http\Controllers\Api\MarketingController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use Illuminate\Support\Facades\Route;

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
