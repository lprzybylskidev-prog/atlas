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

    public function test_dashboard_alias_is_not_supported(): void
    {
        $this->get('/dashboard')->assertNotFound();
    }
}
