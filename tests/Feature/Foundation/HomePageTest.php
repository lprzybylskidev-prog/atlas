<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Tests\TestCase;

final class HomePageTest extends TestCase
{
    public function test_home_page_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_legacy_dashboard_path_redirects_to_home(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }
}
