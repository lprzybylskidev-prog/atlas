<?php

declare(strict_types=1);

use App\Modules\Core\Settings\Presentation\Http\Controllers\UpdateLocalePreferenceController;
use App\Modules\Core\Settings\Presentation\Http\Controllers\UpdateThemePreferenceController;
use Illuminate\Support\Facades\Route;

Route::post('/locale', UpdateLocalePreferenceController::class)->name('locale.update');
Route::post('/theme', UpdateThemePreferenceController::class)->name('theme.update');
