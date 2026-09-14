<?php

namespace Tests\Unit;

use App\Http\Requests\Web\Purchasing\SubmitPurchaserCartRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubmitPurchaserCartRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_purchase_roles_can_authorize_submission(): void
    {
        foreach (['admin', 'purchase'] as $roleName) {
            Role::findOrCreate($roleName);
            $user = User::factory()->create();
            $user->assignRole($roleName);

            $request = SubmitPurchaserCartRequest::create('/purchaser/carts/submit', 'POST');
            $request->setUserResolver(fn (): User => $user);

            $this->assertTrue($request->authorize());
        }
    }
}
