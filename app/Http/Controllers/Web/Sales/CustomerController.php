<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\DTOs\Sales\CustomerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Sales\StoreCustomerRequest;
use App\Http\Requests\Web\Sales\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\Sales\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = $this->service->paginate(
            perPage: 20,
            search: $request->input('search'),
            type: $request->input('type'),
        );

        $search = trim((string) $request->input('search', ''));

        $shopDestinations = Shop::query()
            ->with([
                'users' => fn ($query) => $query->with('roles')->orderBy('name'),
            ])
            ->withCount('orders')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('users', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->get();

        return view('sales.customers.index', compact('customers', 'shopDestinations'));
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('sales.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->service->create(CustomerData::fromRequest($request));

        return redirect()->route('sales.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('sales.customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->service->update($customer, CustomerData::fromRequest($request));

        return redirect()->route('sales.customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $this->service->delete($customer);

        return redirect()->route('sales.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
}
