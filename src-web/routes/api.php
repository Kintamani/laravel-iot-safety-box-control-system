<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;

Route::post('/device/heartbeat', [DeviceController::class, 'heartbeat']);
Route::post('/device/network', [DeviceController::class, 'network']);
Route::post('/device/scan', [DeviceController::class, 'scan']);

Route::get('/devices', [DeviceController::class, 'devices']);
Route::get('/orders/{order}', [DeviceController::class, 'order']);
