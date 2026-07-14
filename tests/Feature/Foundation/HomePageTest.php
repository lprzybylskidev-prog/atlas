<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_returns_atlas_landing_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('<title>Atlas</title>', false);
    }
}
