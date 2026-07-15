<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteEnquiry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebsiteEnquiryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_enquiry_from_homepage_is_saved(): void
    {
        $this->withoutMiddleware();

        $response = $this->post(route('website-enquiries.store'), [
            'name' => 'Ameen Traders',
            'phone' => '9876543210',
            'customer_type' => 'Wholesale buyer',
            'required_date' => '2026-07-15',
            'message' => 'Need tomatoes 20 kg and onions 30 kg.',
            'source_page' => 'home',
        ]);

        $response
            ->assertRedirect(route('home').'#contact')
            ->assertSessionHas('success', 'Enquiry received. Our team will contact you shortly.');

        $this->assertDatabaseHas('website_enquiries', [
            'name' => 'Ameen Traders',
            'phone' => '9876543210',
            'customer_type' => 'Wholesale buyer',
            'message' => 'Need tomatoes 20 kg and onions 30 kg.',
            'source_page' => 'home',
        ]);
    }

    public function test_admin_can_view_website_enquiries(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        WebsiteEnquiry::factory()->create([
            'name' => 'City Market',
            'phone' => '9000000001',
            'customer_type' => 'Shop or supermarket',
            'message' => 'Need bananas and oranges for Friday.',
            'source_page' => 'products',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.enquiries.index'));

        $response
            ->assertOk()
            ->assertSee('Website enquiries')
            ->assertSee('City Market')
            ->assertSee('Need bananas and oranges for Friday.')
            ->assertSee('Marketplace');
    }

    public function test_non_admin_cannot_view_website_enquiries(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.enquiries.index'));

        $response->assertForbidden();
    }
}
