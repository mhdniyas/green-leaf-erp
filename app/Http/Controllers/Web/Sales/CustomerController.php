<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\DTOs\Sales\CustomerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Sales\StoreCustomerRequest;
use App\Http\Requests\Web\Sales\StoreSalesShopRequest;
use App\Http\Requests\Web\Sales\UpdateCustomerRequest;
use App\Http\Requests\Web\Sales\UpdateSalesShopRequest;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopPriceGroup;
use App\Services\Sales\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $search = trim((string) $request->input('search', ''));

        $shopDestinations = Shop::query()
            ->with([
                'client',
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
        $clients = Client::query()->active()->orderBy('name')->get();
        $priceGroups = ShopPriceGroup::query()->active()->orderBy('name')->get();

        return view('sales.customers.index', compact('clients', 'priceGroups', 'shopDestinations'));
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('sales.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->service->create(CustomerData::fromRequest($request));

        return redirect()
            ->route('sales.customers.index')
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

        return redirect()
            ->route('sales.customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $this->service->delete($customer);

        return redirect()
            ->route('sales.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    public function storeShop(StoreSalesShopRequest $request): RedirectResponse
    {
        $client = $this->resolveClient($request);
        $destinationType = $request->string('destination_type')->toString();

        $data = [
            'name' => $request->string('name')->toString(),
            'code' => $request->string('code')->toString(),
            'warehouse_tag' => $request->input('warehouse_tag') ?: null,
            'shop_price_group_id' => $request->integer('shop_price_group_id') ?: null,
            'allow_grade_b_purchase' => false,
            'shop_purchasing_enabled' => $request->boolean('shop_purchasing_enabled'),
            'client_id' => $destinationType === 'client' ? $client?->id : null,
            'status' => $request->string('status')->toString(),
            'accounting_mode' => $destinationType === 'client' ? 'owned' : 'regular',
            'accounting_enabled' => $destinationType === 'client',
            'address' => $request->input('address') ?: null,
            'contact_name' => $request->input('contact_name') ?: null,
            'contact_phone' => $request->input('contact_phone') ?: null,
        ];

        if ($request->user()?->hasRole('admin') && ($request->has('allowed_product_grades') || $request->has('enable_multi_grade_ordering') || $request->has('multi_grade_ordering_enabled'))) {
            $rawGrades = (array) $request->input('allowed_product_grades', ['A']);
            $orderableGradeValues = ['A', 'B', 'C'];
            $grades = array_values(array_unique(array_filter(
                array_map(fn ($g) => strtoupper(trim((string) $g)), $rawGrades),
                fn ($g) => in_array($g, $orderableGradeValues, true)
            )));

            $defaultGrade = strtoupper(trim((string) $request->input('default_product_grade', '')));
            if (! in_array($defaultGrade, $grades, true)) {
                $defaultGrade = $grades[0] ?? 'A';
            }

            $orderedGrades = array_values(array_unique(array_merge([$defaultGrade], $grades)));
            $multiGradeEnabled = $request->boolean('enable_multi_grade_ordering', $request->boolean('multi_grade_ordering_enabled', false));

            $data['allowed_product_grades'] = [
                'allowed' => $orderedGrades,
                'default' => $defaultGrade,
                'enable_multi_grade_ordering' => $multiGradeEnabled,
            ];
            $data['allow_grade_b_purchase'] = in_array('B', $orderedGrades, true);
        }

        Shop::query()->create($data);

        return redirect()
            ->route('sales.customers.index')
            ->with('success', 'Shop created successfully.');
    }

    public function updateShop(UpdateSalesShopRequest $request, Shop $shop): RedirectResponse
    {
        $client = $this->resolveClient($request);
        $destinationType = $request->string('destination_type')->toString();

        $data = [
            'name' => $request->string('name')->toString(),
            'code' => $request->string('code')->toString(),
            'warehouse_tag' => $request->input('warehouse_tag') ?: null,
            'shop_price_group_id' => $request->integer('shop_price_group_id') ?: null,
            'allow_grade_b_purchase' => (bool) $shop->allow_grade_b_purchase,
            'shop_purchasing_enabled' => $request->boolean('shop_purchasing_enabled'),
            'client_id' => $destinationType === 'client' ? $client?->id : null,
            'status' => $request->string('status')->toString(),
            'accounting_mode' => $destinationType === 'client' ? 'owned' : 'regular',
            'accounting_enabled' => $destinationType === 'client',
            'address' => $request->input('address') ?: null,
            'contact_name' => $request->input('contact_name') ?: null,
            'contact_phone' => $request->input('contact_phone') ?: null,
        ];

        if ($request->user()?->hasRole('admin') && ($request->has('allowed_product_grades') || $request->has('enable_multi_grade_ordering') || $request->has('multi_grade_ordering_enabled'))) {
            $rawGrades = (array) $request->input('allowed_product_grades', $shop->allowedProductGrades());
            $orderableGradeValues = ['A', 'B', 'C'];
            $grades = array_values(array_unique(array_filter(
                array_map(fn ($g) => strtoupper(trim((string) $g)), $rawGrades),
                fn ($g) => in_array($g, $orderableGradeValues, true)
            )));

            $defaultGrade = strtoupper(trim((string) $request->input('default_product_grade', $shop->defaultProductGrade())));
            if (! in_array($defaultGrade, $grades, true)) {
                $defaultGrade = $grades[0] ?? 'A';
            }

            $orderedGrades = array_values(array_unique(array_merge([$defaultGrade], $grades)));
            $multiGradeEnabled = ($request->has('enable_multi_grade_ordering') || $request->has('multi_grade_ordering_enabled'))
                ? ($request->boolean('enable_multi_grade_ordering') || $request->boolean('multi_grade_ordering_enabled'))
                : $shop->isMultiGradeOrderingEnabled();

            $data['allowed_product_grades'] = [
                'allowed' => $orderedGrades,
                'default' => $defaultGrade,
                'enable_multi_grade_ordering' => $multiGradeEnabled,
            ];
            $data['allow_grade_b_purchase'] = in_array('B', $orderedGrades, true);
        }

        $shop->update($data);

        return redirect()
            ->route('sales.customers.index')
            ->with('success', 'Shop updated successfully.');
    }

    private function resolveClient(Request $request): ?Client
    {
        if ($request->string('destination_type')->toString() !== 'client') {
            return null;
        }

        if ($request->filled('client_name')) {
            $name = trim((string) $request->input('client_name'));

            return Client::query()->firstOrCreate(
                ['code' => $this->uniqueClientCode($name)],
                [
                    'name' => $name,
                    'status' => 'active',
                    'notes' => 'Created from sales shop setup.',
                ],
            );
        }

        return Client::query()->find($request->integer('client_id'));
    }

    private function uniqueClientCode(string $name): string
    {
        $baseCode = Str::of($name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->limit(32, '')
            ->toString() ?: 'CLIENT';
        $code = $baseCode;
        $suffix = 2;

        while (Client::query()->where('code', $code)->exists()) {
            $code = $baseCode.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
