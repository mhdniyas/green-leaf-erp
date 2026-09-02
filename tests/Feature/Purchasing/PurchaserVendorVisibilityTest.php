<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserVendorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'purchaser']);
        Role::firstOrCreate(['name' => 'admin']);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_existing_or_default_purchaser_vendor_visibility_is_all(): void
    {
        $this->assertSame('all', $this->purchaser->vendorVisibility());
        $this->assertTrue($this->purchaser->showsAllVendors());
        $this->assertFalse($this->purchaser->showsRelatedVendorsOnly());
    }

    public function test_purchaser_configured_for_all_sees_all_vendors(): void
    {
        Supplier::factory()->count(3)->create();

        $this->purchaser->update(['vendor_visibility' => 'all']);

        $suppliers = $this->purchaser->scopedSuppliersQuery()->get();

        $this->assertCount(3, $suppliers);
    }

    public function test_purchaser_configured_for_related_sees_only_assigned_suppliers(): void
    {
        $category1 = Category::factory()->create(['is_active' => true]);
        $category2 = Category::factory()->create(['is_active' => true]);

        $product1 = Product::factory()->create(['category_id' => $category1->id]);
        $product2 = Product::factory()->create(['category_id' => $category2->id]);

        $supplier1 = Supplier::factory()->create(['name' => 'Supplier One']);
        $supplier2 = Supplier::factory()->create(['name' => 'Supplier Two']);

        $supplier1->products()->attach($product1->id);
        $supplier2->products()->attach($product2->id);

        $this->purchaser->update([
            'assigned_category_ids' => [$category1->id],
            'vendor_visibility' => 'related',
        ]);

        $suppliers = $this->purchaser->scopedSuppliersQuery()->get();

        $this->assertCount(1, $suppliers);
        $this->assertSame($supplier1->id, $suppliers->first()->id);
    }

    public function test_unrelated_supplier_is_excluded_for_related_purchaser(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $relatedSupplier = Supplier::factory()->create(['name' => 'Related Supplier']);
        $unrelatedSupplier = Supplier::factory()->create(['name' => 'Unrelated Supplier']);

        $relatedSupplier->products()->attach($product->id);

        $this->purchaser->update([
            'assigned_category_ids' => [$category->id],
            'vendor_visibility' => 'related',
        ]);

        $scopedIds = $this->purchaser->scopedSuppliersQuery()->pluck('id')->all();

        $this->assertContains($relatedSupplier->id, $scopedIds);
        $this->assertNotContains($unrelatedSupplier->id, $scopedIds);
    }

    public function test_related_mode_with_zero_assignments_returns_zero_suppliers_and_no_fallback(): void
    {
        Supplier::factory()->count(5)->create();

        $this->purchaser->update([
            'assigned_category_ids' => null,
            'vendor_visibility' => 'related',
        ]);

        $suppliers = $this->purchaser->scopedSuppliersQuery()->get();

        $this->assertCount(0, $suppliers);
    }

    public function test_admin_and_vendor_management_screens_are_unaffected(): void
    {
        Supplier::factory()->count(4)->create();

        $this->purchaser->update([
            'assigned_category_ids' => null,
            'vendor_visibility' => 'related',
        ]);

        $adminScopedSuppliers = $this->admin->scopedSuppliersQuery()->get();

        $this->assertCount(4, $adminScopedSuppliers);
    }

    public function test_api_supplier_index_respects_vendor_visibility_preference(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $supplier1 = Supplier::factory()->create(['name' => 'API Supplier A']);
        $supplier2 = Supplier::factory()->create(['name' => 'API Supplier B']);

        $supplier1->products()->attach($product->id);

        $this->purchaser->update([
            'assigned_category_ids' => [$category->id],
            'vendor_visibility' => 'related',
        ]);

        $service = app(SupplierService::class);
        $paginated = $service->paginate(15, $this->purchaser);

        $this->assertCount(1, $paginated->items());
        $this->assertSame($supplier1->id, $paginated->items()[0]->id);
    }

    public function test_dropdown_and_vendor_selectors_respect_vendor_visibility(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $supplier = Supplier::factory()->create(['name' => 'Dropdown Supplier']);
        $supplier->products()->attach($product->id);

        Supplier::factory()->create(['name' => 'Other Supplier']);

        $this->purchaser->update([
            'assigned_category_ids' => [$category->id],
            'vendor_visibility' => 'related',
        ]);

        $scoped = $this->purchaser->scopedSuppliersQuery()->get();

        $this->assertCount(1, $scoped);
        $this->assertSame('Dropdown Supplier', $scoped->first()->name);
    }

    public function test_direct_access_to_unrelated_supplier_is_blocked_for_related_purchaser(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Restricted Supplier']);

        $this->purchaser->update([
            'assigned_category_ids' => null,
            'vendor_visibility' => 'related',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.suppliers.show', ['supplier' => $supplier->public_uuid ?? $supplier->id, 'date' => today()->toDateString()]));

        $response->assertNotFound();
    }
}
