<?php

use App\Http\Controllers\Api\CorridorController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\SizeCurveController;
use App\Http\Controllers\Api\SpreadSeriesController;
use App\Http\Controllers\Api\TripwireController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('meta', [MetaController::class, 'index']);
    Route::get('corridors', [CorridorController::class, 'index']);
    Route::get('leaderboard', [LeaderboardController::class, 'index']);
    Route::get('spread-series', [SpreadSeriesController::class, 'index']);
    Route::get('size-curve', [SizeCurveController::class, 'index']);
    Route::get('settlements', [SettlementController::class, 'index']);
    Route::get('tripwires', [TripwireController::class, 'index']);
});
