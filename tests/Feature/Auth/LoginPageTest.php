<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure the login page remains reachable for guests.
     */
    public function test_guest_can_view_the_login_page(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Go to top');
        $response->assertSee('Go to bottom');
    }
}
