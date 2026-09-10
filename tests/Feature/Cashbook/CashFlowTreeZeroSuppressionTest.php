<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\DTOs\Cashbook\CashFlowTreeNode;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashFlowTreeZeroSuppressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');
    }

    public function test_node_blade_skips_zero_metrics_and_only_shows_with_values(): void
    {
        // Node with zero Opening, zero Out, but non-zero In and Closing
        $node = new CashFlowTreeNode(
            id: 'test_node_1',
            title: 'Sample Testing Node',
            subtitle: 'Checking zero suppression',
            badge: 'Test',
            entityType: 'test',
            openingBalance: 0.0,
            totalIn: 45000.0,
            totalOut: 0.0,
            closingBalance: 45000.0,
            netChange: 45000.0,
            children: [],
            movements: []
        );

        $view = $this->blade(
            '@include("admin.cashbook.cash-flow-tree._node", ["node" => $node, "level" => 0])',
            ['node' => $node]
        );

        // Should NOT contain ₹0.00 anywhere
        $view->assertDontSeeText('₹0.00');
        $view->assertDontSeeText('Opening');
        $view->assertDontSeeText('Out (-)');

        // Should contain In (+) and Closing with their values
        $view->assertSeeText('In (+)');
        $view->assertSeeText('₹45,000.00');
        $view->assertSeeText('Closing');
    }

    public function test_node_blade_hides_entire_metric_block_if_all_metrics_are_zero(): void
    {
        $emptyNode = new CashFlowTreeNode(
            id: 'test_node_empty',
            title: 'Zero Balance Entity',
            subtitle: 'No figures at all',
            badge: 'Test',
            entityType: 'test',
            openingBalance: 0.0,
            totalIn: 0.0,
            totalOut: 0.0,
            closingBalance: 0.0,
            netChange: 0.0,
            children: [],
            movements: []
        );

        $view = $this->blade(
            '@include("admin.cashbook.cash-flow-tree._node", ["node" => $node, "level" => 0])',
            ['node' => $emptyNode]
        );

        $view->assertDontSeeText('₹0.00');
        $view->assertDontSeeText('Opening');
        $view->assertDontSeeText('In (+)');
        $view->assertDontSeeText('Out (-)');
        $view->assertDontSeeText('Closing');
    }

    public function test_inactive_vendors_with_zero_balances_are_skipped_from_tree(): void
    {
        CompanyAccount::create([
            'name' => 'Company HDFC',
            'bank_name' => 'HDFC BANK',
            'account_type' => 'bank',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'enabled' => true,
        ]);

        // Create 2 vendors: one completely inactive (0 balance, 0 invoices), one not in this month
        $inactiveVendor1 = Supplier::factory()->create(['name' => 'Completely Inactive Vendor']);
        $inactiveVendor2 = Supplier::factory()->create(['name' => 'Zero Balance Vendor']);

        $service = app(CashFlowTreeService::class);
        $result = $service->build('2026-09');

        $vendorsBranch = collect($result['tree']->children)->firstWhere('id', 'branch_vendors');
        $this->assertNotNull($vendorsBranch);

        // Neither inactive vendor should be included in childNodes
        $vendorIds = collect($vendorsBranch->children)->pluck('id')->all();
        $this->assertNotContains("vendor_{$inactiveVendor1->id}", $vendorIds);
        $this->assertNotContains("vendor_{$inactiveVendor2->id}", $vendorIds);
    }
}
