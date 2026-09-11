<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\DTOs\Cashbook\CashFlowTreeNode;
use App\DTOs\Cashbook\MoneyMovement;
use App\Http\Controllers\Controller;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashFlow\CashFlowTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CashFlowTreeController extends Controller
{
    public function __construct(
        private readonly CashFlowTreeService $treeService
    ) {}

    public function index(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $month = (string) $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $filters = [
            'shop_id' => $request->filled('shop_id') ? (int) $request->query('shop_id') : null,
            'purchaser_id' => $request->filled('purchaser_id') ? (int) $request->query('purchaser_id') : null,
            'vendor_id' => $request->filled('vendor_id') ? (int) $request->query('vendor_id') : null,
            'company_account_id' => $request->filled('company_account_id') ? (int) $request->query('company_account_id') : null,
        ];

        $result = $this->treeService->build($month, array_filter($filters));

        $shops = Shop::query()->orderBy('name')->get();
        $purchasers = User::query()
            ->whereHas('purchaserCredits')
            ->orWhereHas('roles', fn ($q) => $q->where('name', 'purchaser'))
            ->orderBy('name')
            ->get();
        $vendors = Supplier::query()->orderBy('name')->get();
        $accounts = CompanyAccount::query()->where('enabled', true)->orderBy('name')->get();

        return view('admin.cashbook.cash-flow-tree.index', [
            'month' => $month,
            'filters' => $filters,
            'tree' => $result['tree'],
            'summary' => $result['summary'],
            'mapData' => $result['map_data'],
            'shops' => $shops,
            'purchasers' => $purchasers,
            'vendors' => $vendors,
            'accounts' => $accounts,
        ]);
    }

    public function edgeDrilldown(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $month = (string) $request->query('month', now()->format('Y-m'));
        $fromId = (string) $request->query('from_id', '');
        $toId = (string) $request->query('to_id', '');
        $movementType = (string) $request->query('movement_type', '');

        $result = $this->treeService->build($month);
        $movements = $this->treeService->findEdgeMovements($result['movements'], $fromId, $toId, $movementType ?: null);

        return response()->json([
            'status' => 'success',
            'from_id' => $fromId,
            'to_id' => $toId,
            'movement_count' => $movements->count(),
            'total_amount' => round((float) $movements->sum('amount'), 2),
            'movements' => $movements->map(fn (MoneyMovement $m): array => $m->toArray())->values()->all(),
        ]);
    }

    public function drilldown(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $month = (string) $request->query('month', now()->format('Y-m'));
        $nodeId = (string) $request->query('node_id', '');

        $filters = [
            'shop_id' => $request->filled('shop_id') ? (int) $request->query('shop_id') : null,
            'purchaser_id' => $request->filled('purchaser_id') ? (int) $request->query('purchaser_id') : null,
            'vendor_id' => $request->filled('vendor_id') ? (int) $request->query('vendor_id') : null,
            'company_account_id' => $request->filled('company_account_id') ? (int) $request->query('company_account_id') : null,
        ];

        $result = $this->treeService->build($month, array_filter($filters));
        $targetNode = $this->findNode($result['tree'], $nodeId);

        if (! $targetNode) {
            return response()->json([
                'status' => 'not_found',
                'node_id' => $nodeId,
                'movements' => [],
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'node_id' => $targetNode->id,
            'title' => $targetNode->title,
            'badge' => $targetNode->badge,
            'opening_balance' => $targetNode->openingBalance,
            'total_in' => $targetNode->totalIn,
            'total_out' => $targetNode->totalOut,
            'closing_balance' => $targetNode->closingBalance,
            'movements' => array_map(fn (MoneyMovement $m): array => $m->toArray(), $targetNode->movements),
        ]);
    }

    private function findNode(CashFlowTreeNode $node, string $nodeId): ?CashFlowTreeNode
    {
        if ($node->id === $nodeId) {
            return $node;
        }

        foreach ($node->children as $child) {
            $found = $this->findNode($child, $nodeId);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function ensureMainAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('accounts')
            || $user->hasRole('accountant')
            || $user->hasRole('account')
            || $user->hasRole('manager')
            || (property_exists($user, 'is_admin') && $user->is_admin)
            || $user->hasAnyPermission([
                'accounting.report.view',
                'accounting.dashboard.view',
                'accounting.ledger.view',
                'finance.dashboard.view',
            ])
        ) {
            return;
        }

        abort(403, 'Unauthorized access to cash flow tree.');
    }
}
