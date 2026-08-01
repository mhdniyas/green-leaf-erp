<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserBillUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PurchaserBillUpdateAccessController extends Controller
{
    public function store(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('purchaser'), 403, 'Unauthorized access.');

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('purchaserCart')
            ->firstOrFail();

        $validated = $request->validate([
            'requested_business_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        PurchaserBillUpdateRequest::query()->updateOrCreate(
            [
                'purchase_invoice_id' => $invoice->id,
                'requested_by' => $request->user()->id,
                'status' => 'pending',
            ],
            [
                'purchaser_cart_id' => $invoice->purchaser_cart_id,
                'current_business_date' => $invoice->purchaserCart?->business_date,
                'requested_business_date' => filled($validated['requested_business_date'] ?? null)
                    ? Carbon::parse($validated['requested_business_date'])->toDateString()
                    : null,
                'reason' => trim((string) $validated['reason']),
            ],
        );

        return back()->with('success', 'Bill update access request sent for admin approval.');
    }

    public function approve(Request $request, PurchaserBillUpdateRequest $billUpdateRequest): RedirectResponse
    {
        $this->ensureAdminCanReview($request);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $billUpdateRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
            'expires_at' => now()->addDay(),
        ]);

        return back()->with('success', 'Purchaser bill update access approved for 24 hours.');
    }

    public function reject(Request $request, PurchaserBillUpdateRequest $billUpdateRequest): RedirectResponse
    {
        $this->ensureAdminCanReview($request);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $billUpdateRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
            'expires_at' => null,
        ]);

        return back()->with('success', 'Purchaser bill update access rejected.');
    }

    private function ensureAdminCanReview(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->hasRole('admin') || $user->hasRole('purchase') || $user->can('admin.settings.update')),
            403,
            'Unauthorized access.'
        );
    }
}
