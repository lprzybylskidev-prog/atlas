<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function (): void {
    Route::get('/admin', fn () => Inertia::render('Admin/SystemStatus'))->name('admin.system-status');
});
