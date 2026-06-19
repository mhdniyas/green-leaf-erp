<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\StoreShopOwnerRegistrationRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopOwnerRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.shop-owner-register');
    }

    public function store(StoreShopOwnerRegistrationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $shop = Shop::create([
                'code' => $this->nextShopCode(),
                'name' => $validated['shop_name'],
                'status' => 'pending_approval',
                'address' => $validated['address'] ?: null,
                'contact_name' => $validated['owner_name'],
                'contact_phone' => '+91'.$validated['phone'],
            ]);

            $user = User::create([
                'name' => $validated['owner_name'],
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
                'shop_id' => $shop->id,
                'registration_status' => 'pending',
            ]);

            $user->syncRoles(['shop']);
        });

        return redirect()->route('login')
            ->with('status', 'Registration submitted. Admin approval is required before shop-owner login is enabled.');
    }

    private function nextShopCode(): string
    {
        $nextNumber = (int) Shop::query()->count() + 1;

        do {
            $code = sprintf('REG-SHOP-%03d', $nextNumber);
            $nextNumber++;
        } while (Shop::query()->where('code', $code)->exists());

        return $code;
    }
}
