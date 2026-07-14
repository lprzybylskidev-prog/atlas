<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->get('/login', fn () => Inertia::render('Auth/Login'))->name('login');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/admin', fn () => Inertia::render('Admin/SystemStatus'))->name('admin.system-status');
});
