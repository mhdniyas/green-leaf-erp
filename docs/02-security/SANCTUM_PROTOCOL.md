# SANCTUM PROTOCOL

**Green Leaf ERP — Laravel Sanctum Implementation Guide**
**Version**: 1.0.0 | **Package**: laravel/sanctum ^4.3

> Sanctum is this ERP's authentication backbone. All API access goes through it.
> Read this before implementing any authentication feature.

---

## HOW SANCTUM WORKS IN THIS PROJECT

```
Mobile App / SPA / External API Client
        │
        │  POST /api/v1/auth/login
        │  {email: "...", password: "..."}
        ▼
    ┌───────────────┐
    │   Sanctum     │  → Validates credentials → Creates PersonalAccessToken
    └───────────────┘
        │
        │  Response: {token: "1|abc123xyz..."}
        ▼
    Client stores token

    Every subsequent request:
    Authorization: Bearer 1|abc123xyz...
        │
        ▼
    Sanctum middleware resolves User from token
    → Request continues with authenticated user
```

---

## CONFIGURATION

```php
// config/sanctum.php

'expiration' => 60 * 24 * 7,   // Token expires in 7 days (minutes)

'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort()
))),

'guard' => ['web'],

'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
```

---

## TOKEN CREATION

```php
// Create token with abilities (scopes)
$token = $user->createToken(
    name: 'api-access',
    abilities: ['read', 'write'],        // Define what this token can do
    expiresAt: now()->addDays(7)         // Explicit expiration
)->plainTextToken;

// Create read-only token
$token = $user->createToken('read-only', ['read'])->plainTextToken;

// Token for specific device
$token = $user->createToken(
    name: "iPhone - {$user->email}",
    abilities: ['*'],
)->plainTextToken;
```

---

## AUTH CONTROLLER PATTERN

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::unauthorized('Invalid credentials');
        }

        $user  = Auth::user();
        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        return ApiResponse::success([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 'Login successful');
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(new UserResource(auth()->user()));
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function logoutAll(): JsonResponse
    {
        auth()->user()->tokens()->delete();

        return ApiResponse::success(null, 'All sessions terminated');
    }
}
```

---

## PROTECTING ROUTES

```php
// routes/api.php

// All protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Require specific ability/scope
    Route::middleware(['auth:sanctum', 'abilities:write'])->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
    });
});
```

---

## TOKEN ABILITIES (SCOPES)

```php
// Define abilities on token creation
$token = $user->createToken('mobile-app', ['inventory:read', 'sales:write']);

// Check ability in middleware
$request->user()->tokenCan('inventory:read');

// Check in FormRequest
public function authorize(): bool
{
    return $this->user()->tokenCan('inventory:write')
        && $this->user()->can('create', Product::class);
}
```

---

## USER MODEL REQUIREMENTS

```php
// app/Models/User.php — these traits MUST be present
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles;
    // ...
}
```

---

## TOKEN MANAGEMENT FOR ADMINS

```php
// List all tokens for a user
$user->tokens;

// Revoke a specific token
$user->tokens()->where('id', $tokenId)->delete();

// Revoke all tokens (force logout all devices)
$user->tokens()->delete();

// Token lifespan check
$token->expires_at; // Carbon or null
$token->isExpired(); // boolean
```

---

## RATE LIMITING ON AUTH ROUTES

```php
// routes/api.php — ALWAYS apply rate limiting on auth routes
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/auth/login', ...);
    Route::post('/auth/register', ...);
    Route::post('/auth/forgot-password', ...);
});
```

---

## TESTING WITH SANCTUM

```php
// In feature tests — act as authenticated user
$user = User::factory()->create();
$user->assignRole('admin');

// Method 1: actingAs (preferred for feature tests)
$this->actingAs($user, 'sanctum')
    ->getJson('/api/v1/products')
    ->assertOk();

// Method 2: With specific token abilities
Sanctum::actingAs($user, ['inventory:read']);
$this->getJson('/api/v1/products')->assertOk();

// Test unauthenticated access
$this->getJson('/api/v1/products')
    ->assertUnauthorized();
```

---

**Owner**: Engineering Team | **Project**: Green Leaf ERP
**Package**: laravel/sanctum ^4.3
