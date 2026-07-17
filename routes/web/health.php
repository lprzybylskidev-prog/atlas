<?php

declare(strict_types=1);

use App\Modules\Core\Health\Presentation\Http\Controllers\LivenessController;
use App\Modules\Core\Health\Presentation\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', LivenessController::class)->name('health.live');
Route::get('/health/ready', ReadinessController::class)->name('health.ready');
