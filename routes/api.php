<?php

use App\Http\Controllers\Api\ProductController as ApiProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->group(function () {
    Route::get('/products', [ApiProductController::class, 'index']);
    Route::get('/products/{identifier}', [ApiProductController::class, 'show']);
});
