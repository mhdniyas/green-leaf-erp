<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\VendorAdvance;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorAdvanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $query = VendorAdvance::query()->with('supplier');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->input('supplier_id'));
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'bill_pending' || $status === 'open') {
                $query->where('amount_remaining', '>', 0);
            } elseif ($status === 'settled' || $status === 'used') {
                $query->where(function ($q): void {
                    $q->where('amount_remaining', '<=', 0)
                        ->orWhere('status', 'used');
                });
            } elseif ($status === 'partial') {
                $query->where('amount_remaining', '>', 0)
                    ->whereColumn('amount_remaining', '<', 'amount_original');
            }
        }

        if ($request->filled('date')) {
            $query->whereDate('business_date', $request->input('date'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('contact', 'like', "%{$search}%");
                    });
            });
        }

        $advances = $query->orderByDesc('business_date')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 25));

        $formatted = $advances->through(fn (VendorAdvance $advance): array => $this->formatAdvance($advance));

        return ApiResponse::paginated($formatted);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $advance = DB::transaction(function () use ($validated, $request): VendorAdvance {
            $amount = round((float) $validated['amount'], 2);
            $businessDate = ! empty($validated['date'])
                ? Carbon::parse($validated['date'])->toDateString()
                : now()->toDateString();

            return VendorAdvance::query()->create([
                'supplier_id' => (int) $validated['supplier_id'],
                'source_settlement_id' => null,
                'amount_original' => $amount,
                'amount_remaining' => $amount,
                'business_date' => $businessDate,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'open',
                'created_by' => (int) $request->user()->id,
            ]);
        });

        $advance->load('supplier');

        return ApiResponse::success(
            $this->formatAdvance($advance),
            'Vendor advance recorded successfully (BILL PENDING)',
            201
        );
    }

    public function show(VendorAdvance $vendorAdvance, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        return ApiResponse::success(
            $this->formatAdvance($vendorAdvance->load('supplier'))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAdvance(VendorAdvance $advance): array
    {
        $remaining = (float) $advance->amount_remaining;
        $original = (float) $advance->amount_original;

        $statusKey = 'bill_pending';
        if ($remaining <= 0 || $advance->status === 'used') {
            $statusKey = 'settled';
        } elseif ($remaining < $original) {
            $statusKey = 'partial';
        }

        return [
            'id' => $advance->id,
            'supplier_id' => $advance->supplier_id,
            'supplier_name' => $advance->supplier?->name ?? 'Unknown Vendor',
            'supplier_code' => $advance->supplier?->code ?? '',
            'amount_original' => $original,
            'amount_remaining' => $remaining,
            'amount' => $original,
            'business_date' => $advance->business_date?->toDateString(),
            'payment_method' => $advance->payment_method ?: 'cash',
            'reference' => $advance->reference,
            'notes' => $advance->notes,
            'status' => $statusKey,
            'status_label' => $advance->status_label,
            'is_bill_pending' => $statusKey === 'bill_pending',
            'created_at' => $advance->created_at?->toIso8601String(),
        ];
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->hasRole(['admin', 'purchase', 'purchaser', 'warehouse_receiver'])) {
            return;
        }

        if ($user->canAny(['purchasing.grn.create', 'purchasing.supplier.view', 'purchasing.order.create'])) {
            return;
        }

        abort(403, 'Unauthorized to manage vendor advances.');
    }
}
