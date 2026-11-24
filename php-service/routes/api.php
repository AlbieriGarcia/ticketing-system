<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReservationController;
use App\Models\Event;
use Illuminate\Support\Facades\Route;

Route::pattern('event', '[0-9]+');
Route::pattern('reservation', '[0-9]+');

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::get('{event}', [EventController::class, 'show']);
    Route::get('{event}/availability', [EventController::class, 'availability']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [EventController::class, 'store'])->can('create', Event::class);
        Route::put('{event}', [EventController::class, 'update'])->can('update', 'event');
        Route::patch('{event}/status', [EventController::class, 'changeStatus'])->can('changeStatus', 'event');
        Route::post('{event}/reservations', [ReservationController::class, 'store']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('reservations/{reservation}')->middleware('can:manage,reservation')->group(function () {
        Route::get('/', [ReservationController::class, 'show']);
        Route::post('confirm', [ReservationController::class, 'confirm']);
        Route::post('cancel', [ReservationController::class, 'cancel']);
    });

    Route::get('me/purchases', [PurchaseController::class, 'index']);
});
