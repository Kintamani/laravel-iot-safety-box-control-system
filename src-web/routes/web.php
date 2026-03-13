<?php

use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\Cms\OrderController;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('cms.dashboard');
});

Route::prefix('cms')->name('cms.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/import', [OrderController::class, 'import'])->name('orders.import');
    Route::post('/orders/{order}/qr', [OrderController::class, 'generateQr'])->name('orders.qr');
});

Route::get('/orders/{order}', [CustomerController::class, 'show'])->name('orders.show');
