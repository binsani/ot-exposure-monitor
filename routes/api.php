<?php

use App\Http\Controllers\Api\AgentReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:agent')->prefix('agent/v1')->group(function (): void {
    Route::get('/assets', [AgentReportController::class, 'assets']);
    Route::post('/reports', [AgentReportController::class, 'store']);
});
