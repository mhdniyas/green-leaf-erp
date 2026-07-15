<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    public function test_homepage_loads_as_public_company_landing_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Green Leaf Traders')
            ->assertSee('Fresh fruits and vegetables, delivered daily.')
            ->assertSee('Wholesale and retail fresh produce')
            ->assertSee('Fresh stock every day')
            ->assertSee('Request today’s price')
            ->assertSee('Login')
            ->assertDontSee('Staff Management');
    }

    public function test_products_page_loads_as_marketplace_with_enquiry_actions(): void
    {
        $response = $this->get('/products');

        $response
            ->assertOk()
            ->assertSee('Product marketplace')
            ->assertSee('Tomato')
            ->assertSee('Bananas')
            ->assertSee('Ask about this product')
            ->assertSee('Fresh produce for homes and businesses.')
            ->assertDontSee('ERP')
            ->assertDontSee('₹')
            ->assertDontSee('Staff Management');
    }
}
