<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_response_uses_atlas_inertia_error_page(): void
    {
        $user = User::factory()->create();
        Route::middleware('web')->get('/error-page-test-forbidden', static fn () => abort(403));

        $this->actingAs($user)
            ->get('/error-page-test-forbidden')
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 403));
    }

    public function test_not_found_response_uses_atlas_inertia_error_page(): void
    {
        $this->get('/missing-page')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 404));
    }
}
