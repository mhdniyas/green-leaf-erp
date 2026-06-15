<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\DTOs\Purchasing\SupplierData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StoreSupplierRequest;
use App\Http\Requests\Web\Purchasing\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\Purchasing\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Supplier::class);

        $suppliers = $this->service->paginate(20);

        return view('purchase-manager.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('purchase-manager.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->service->create(SupplierData::fromRequest($request));

        return redirect()->route('purchasing.suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('purchase-manager.suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->service->update($supplier, SupplierData::fromRequest($request));

        return redirect()->route('purchasing.suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('delete', $supplier);

        $this->service->delete($supplier);

        return redirect()->route('purchasing.suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }

    public function requestCreditApproval(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless(
            $request->user()->hasRole('purchase') || $request->user()->hasRole('admin') || $request->user()->hasRole('purchaser'),
            403,
        );

        $validated = $request->validate([
            'credit_approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $supplier->update([
            'credit_approval_requested_at' => now(),
            'credit_approval_requested_by' => $request->user()->id,
            'credit_approval_note' => $validated['credit_approval_note'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Credit approval request sent for this supplier.');
    }

    public function approveCreditApproval(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless(
            $request->user()->hasRole('purchase') || $request->user()->hasRole('admin') || $request->user()->can('purchasing.supplier.update'),
            403,
        );

        $supplier->update([
            'credit_approved' => true,
            'credit_approved_at' => now(),
            'credit_approved_by' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Supplier credit approved successfully.');
    }
}
