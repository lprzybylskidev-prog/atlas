<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Tests\TestCase;

final class HomePageTest extends TestCase
{
    public function test_home_page_redirects_to_the_application_entrypoint(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }
}
