<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Presentation\Http\Controllers\ActiveTeamController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\BulkMarkNotificationReadController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\MarkNotificationReadController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\NotificationCenterController;
use App\Modules\Core\Notifications\Presentation\Http\Controllers\RealtimeEventsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function (): void {
    Route::get('/team/select', [ActiveTeamController::class, 'select'])->name('team.select');
    Route::post('/team/select', [ActiveTeamController::class, 'store'])->name('team.select.store');
});

Route::middleware(['auth', 'route.permission'])->group(function (): void {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/notifications', NotificationCenterController::class)->name('notifications.index');
    Route::post('/notifications/read', BulkMarkNotificationReadController::class)->name('notifications.read.bulk');
    Route::post('/notifications/{notification}/read', MarkNotificationReadController::class)->name('notifications.read');
    Route::get('/realtime/events', RealtimeEventsController::class)->name('notifications.realtime.events');
    Route::post('/team/switch', [ActiveTeamController::class, 'switch'])->name('team.switch');
});
