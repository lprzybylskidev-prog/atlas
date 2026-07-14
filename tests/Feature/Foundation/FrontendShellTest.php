<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class FrontendShellTest extends TestCase
{
    public function test_login_page_renders_as_inertia_auth_layout_entry(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Auth/Login')
                    ->where('locale', 'pl')
                    ->where('auth.user', null),
            );
    }

    public function test_application_and_admin_previews_require_authentication(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_application_and_admin_previews(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('navigation.breadcrumbs', 1)
                ->where('navigation.breadcrumbs.0.label', 'Pulpit')
                ->where('navigation.breadcrumbs.0.url', 'http://localhost:8000'));

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/SystemStatus')
                ->has('navigation.breadcrumbs', 2)
                ->where('navigation.breadcrumbs.0.label', 'Admin')
                ->where('navigation.breadcrumbs.0.url', null)
                ->where('navigation.breadcrumbs.1.label', 'Dashboard')
                ->where('navigation.breadcrumbs.1.url', null));
    }
}
