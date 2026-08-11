<?php

declare(strict_types=1);

use App\Modules\Core\Identity\Presentation\Http\Controllers\ConfirmPasswordController;
use App\Modules\Core\Identity\Presentation\Http\Controllers\RequestPasswordResetLinkController;
use App\Modules\Core\Identity\Presentation\Http\Controllers\ShowForgotPasswordController;
use App\Modules\Core\Identity\Presentation\Http\Controllers\ShowResetPasswordController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->get('/login', fn () => Inertia::render('Auth/Login'))->name('login');

Route::middleware('auth')->group(function (): void {
    Route::get('/user/confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
    Route::post('/user/confirm-password', [ConfirmPasswordController::class, 'store'])->name('password.confirm.store');
});

Route::middleware(['guest', 'throttle:auth.password-reset'])
    ->post('/forgot-password', RequestPasswordResetLinkController::class)
    ->name('password.email');

Route::middleware('guest')->get('/forgot-password', ShowForgotPasswordController::class)->name('password.request');
Route::middleware('guest')->get('/reset-password/{token}', ShowResetPasswordController::class)->name('password.reset');
