<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_page_lists_multiple_shop_demo_accounts(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('shop@greenleaf.com');
        $response->assertSee('shop-budegere@greenleaf.com');
        $response->assertSee('shop-grancity@greenleaf.com');
        $response->assertSee('shop-ashirwad@greenleaf.com');
    }
}
