<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use App\Models\Shop;
use App\Models\User;
use App\Services\HR\EmployeeSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    /**
     * Show the login form.
     */
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Show the hidden demo access page.
     */
    public function demo(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.demo-login', [
            'hasDemoAccess' => $request->session()->get('demo_access_granted', false),
            'staffAccounts' => $this->staffAccounts(),
            'shopAccounts' => $this->shopAccounts(),
        ]);
    }

    public function unlockDemoAccess(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page_password' => ['required', 'string'],
        ]);

        if ($validated['page_password'] !== '2525') {
            return back()->withErrors(['page_password' => 'The access password is incorrect.']);
        }

        $request->session()->put('demo_access_granted', true);

        return redirect()->route('login.demo');
    }

    public function demoLogin(Request $request): RedirectResponse
    {
        abort_unless($request->session()->get('demo_access_granted', false), 403);

        $validated = $request->validate([
            'account' => ['required', 'string'],
        ]);

        $account = $this->resolveDemoAccount($validated['account']);

        abort_unless($account !== null, 404);

        if (! Auth::attempt([
            'email' => $account['email'],
            'password' => $account['password'],
        ])) {
            return redirect()->route('login.demo')
                ->withErrors(['page_password' => 'The selected demo account is unavailable.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Handle an authentication attempt.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if ($user !== null && $user->isPendingRegistration()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'Your registration is pending admin approval.']);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'These credentials do not match our records.']);
    }

    /**
     * Log the user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * @return array<int, array{key: string, name: string, role: string, email: string, password: string}>
     */
    private function staffAccounts(): array
    {
        return [
            ['key' => 'admin', 'name' => 'Administrator', 'role' => 'Admin', 'email' => 'admin@greenleaf.com', 'password' => 'Admin11'],
            ['key' => 'hr-manager', 'name' => 'HR Manager', 'role' => 'HR', 'email' => 'hr@greenleaf.com', 'password' => 'HrManager13'],
            ['key' => 'purchase-manager', 'name' => 'Purchase Manager', 'role' => 'Purchase', 'email' => 'purchase@greenleaf.com', 'password' => 'Purchase12'],
            ['key' => 'purchaser-niyas', 'name' => 'Purchaser Niyas', 'role' => 'Purchaser', 'email' => 'purchaser@greenleaf.com', 'password' => 'Purchaser14'],
            ['key' => 'purchaser-fallback', 'name' => 'Purchaser Fallback', 'role' => 'Purchaser', 'email' => 'purchaser2@greenleaf.com', 'password' => 'Purchaser15'],
            ['key' => 'warehouse-receiver', 'name' => 'Warehouse Receiver', 'role' => 'Receiver', 'email' => 'receiver@greenleaf.com', 'password' => 'Receiver16'],
        ];
    }

    /**
     * @return array<int, array{key: string, name: string, role: string, email: string, password: string, shop_code: string|null}>
     */
    private function shopAccounts(): array
    {
        return Shop::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function (Shop $shop): array {
                $emailSlug = str($shop->code)->lower()->replace('_', '-');

                return [
                    'key' => 'shop-'.$shop->id,
                    'name' => $shop->name,
                    'role' => 'Shop Owner',
                    'email' => 'shop-'.$emailSlug.'@greenleaf.com',
                    'password' => 'ShopOwner17',
                    'shop_code' => $shop->code,
                ];
            })
            ->all();
    }

    /**
     * @return array{key: string, name: string, role: string, email: string, password: string, shop_code?: string|null}|null
     */
    private function resolveDemoAccount(string $accountKey): ?array
    {
        $staffAccount = collect($this->staffAccounts())->firstWhere('key', $accountKey);
        if ($staffAccount !== null) {
            return $staffAccount;
        }

        $shopAccount = collect($this->shopAccounts())->firstWhere('key', $accountKey);
        if ($shopAccount === null) {
            return null;
        }

        $shop = Shop::query()->find((int) str($accountKey)->after('shop-')->toString());
        if (! $shop) {
            return null;
        }

        $user = User::updateOrCreate(
            ['email' => $shopAccount['email']],
            [
                'name' => $shop->name.' Demo',
                'password' => Hash::make($shopAccount['password']),
                'email_verified_at' => now(),
                'shop_id' => $shop->id,
                'registration_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => null,
            ]
        );

        $user->syncRoles(['shop']);
        $this->employeeSyncService->ensureForUser($user->fresh());

        return $shopAccount;
    }
}
