<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->get('/login', fn () => Inertia::render('Auth/Login'))->name('login');

Route::post('/locale', function (Request $request) {
    $request->validate([
        'locale' => ['required', 'string', 'in:pl,en'],
    ]);

    $locale = $request->string('locale')->toString();

    return redirect()->back(303)->withCookie(cookie(
        name: 'atlas_locale',
        value: $locale,
        minutes: 60 * 24 * 365,
        httpOnly: true,
        sameSite: 'lax',
    ));
})->name('locale.update');

Route::middleware('auth')->group(function (): void {
    Route::get('/', fn () => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/admin', fn () => Inertia::render('Admin/SystemStatus'))->name('admin.system-status');
});
