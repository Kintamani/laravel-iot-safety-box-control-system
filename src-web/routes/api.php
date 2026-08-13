<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;

Route::post('/device/heartbeat', [DeviceController::class, 'heartbeat']);
Route::post('/device/network', [DeviceController::class, 'network']);
Route::post('/device/scan', [DeviceController::class, 'scan']);
Route::post('/devices/{device}/relay-on', [DeviceController::class, 'relayOn']);

Route::get('/devices', [DeviceController::class, 'devices']);
Route::get('/orders/{order}', [DeviceController::class, 'order']);
