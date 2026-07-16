<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Presentation\Http\Controllers\ActiveTeamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function (): void {
    Route::get('/team/select', [ActiveTeamController::class, 'select'])->name('team.select');
    Route::post('/team/select', [ActiveTeamController::class, 'store'])->name('team.select.store');
});

Route::middleware(['auth', 'route.permission'])->group(function (): void {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::post('/team/switch', [ActiveTeamController::class, 'switch'])->name('team.switch');
});
