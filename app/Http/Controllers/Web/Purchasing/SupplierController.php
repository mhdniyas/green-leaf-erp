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

        return view('purchasing.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('purchasing.suppliers.create');
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

        return view('purchasing.suppliers.edit', compact('supplier'));
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
}
