<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class GoogleAnalyticsTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_tag_component_renders_correct_tracking_id(): void
    {
        $rendered = Blade::render('<x-google-tag />');

        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-4FPMS03LRM', $rendered);
        $this->assertStringContainsString("gtag('config', 'G-4FPMS03LRM');", $rendered);
    }

    public function test_welcome_page_contains_google_tag(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-4FPMS03LRM', false);
        $response->assertSee("gtag('config', 'G-4FPMS03LRM');", false);
    }

    public function test_marketplace_page_contains_google_tag(): void
    {
        $response = $this->get(route('marketplace.index'));

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-4FPMS03LRM', false);
        $response->assertSee("gtag('config', 'G-4FPMS03LRM');", false);
    }

    public function test_products_page_contains_google_tag(): void
    {
        $response = $this->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-4FPMS03LRM', false);
        $response->assertSee("gtag('config', 'G-4FPMS03LRM');", false);
    }

    public function test_login_page_contains_google_tag(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('https://www.googletagmanager.com/gtag/js?id=G-4FPMS03LRM', false);
        $response->assertSee("gtag('config', 'G-4FPMS03LRM');", false);
    }

    public function test_authenticated_component_layouts_render_google_tag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        View::share('errors', new ViewErrorBag);

        $templates = [
            '<x-layouts.auth title="Auth Test"><div>Content</div></x-layouts.auth>',
            '<x-layouts.app title="App Test"><div>Content</div></x-layouts.app>',
            '<x-layouts.printing title="Printing Test"><div>Content</div></x-layouts.printing>',
            '<x-layouts.admin title="Admin Test"><div>Content</div></x-layouts.admin>',
            '<x-layouts.accounting title="Accounting Test"><div>Content</div></x-layouts.accounting>',
            '<x-layouts.inventory title="Inventory Test"><div>Content</div></x-layouts.inventory>',
            '<x-layouts.staff title="Staff Test"><div>Content</div></x-layouts.staff>',
        ];

        foreach ($templates as $template) {
            $rendered = Blade::render($template);
            $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-4FPMS03LRM', $rendered);
            $this->assertStringContainsString("gtag('config', 'G-4FPMS03LRM');", $rendered);
        }
    }

    public function test_specialized_layouts_render_google_tag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        View::share('errors', new ViewErrorBag);

        $views = [
            'shop-owner.layouts.app',
            'purchase-manager.layouts.app',
            'admin.cashbook.layouts.app',
            'layouts.auth',
        ];

        foreach ($views as $viewName) {
            $rendered = view($viewName, ['slot' => '<div>Content</div>'])->render();
            $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-4FPMS03LRM', $rendered);
            $this->assertStringContainsString("gtag('config', 'G-4FPMS03LRM');", $rendered);
        }
    }
}
