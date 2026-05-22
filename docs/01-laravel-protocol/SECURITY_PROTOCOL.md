# SECURITY PROTOCOL

**Green Leaf ERP — Security Standards**
**Version**: 1.0.0 | **Classification**: MANDATORY — Non-negotiable

> Security is not optional. Every line of code in this ERP handles business data.
> A breach means financial data, inventory, employee records exposed. Never skip security steps.

---

## 1. AUTHENTICATION (Sanctum)

### How Authentication Works

```
Client                      Server
  │                            │
  │  POST /api/v1/auth/login   │
  │  {email, password}         │
  │ ─────────────────────────► │
  │                            │  Validate credentials
  │                            │  Create Sanctum token
  │  {token: "abc123"}         │
  │ ◄───────────────────────── │
  │                            │
  │  GET /api/v1/products      │
  │  Authorization: Bearer abc123
  │ ─────────────────────────► │
  │                            │  Validate token via Sanctum
  │                            │  Resolve user from token
  │  {data: [...]}             │
  │ ◄───────────────────────── │
```

### Token Handling Rules

```php
// ✅ Correct: Create token with abilities (scopes)
$token = $user->createToken('api-token', ['read', 'write'])->plainTextToken;

// ✅ Correct: Delete token on logout
$request->user()->currentAccessToken()->delete();

// ✅ Correct: Check token ability in middleware
$request->user()->tokenCan('write');

// ❌ Never: Store tokens in client localStorage (prefer httpOnly cookies for SPAs)
// ❌ Never: Return tokens in URL parameters
// ❌ Never: Create tokens without expiration for production
```

### Token Expiration (REQUIRED for production)

```php
// config/sanctum.php
'expiration' => 60 * 24 * 7, // 7 days in minutes
```

---

## 2. AUTHORIZATION (Spatie Permission + Policies)

### Two-layer Authorization

**Layer 1: Route-level** (broad access)
```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'role:admin|manager'])->group(function () {
    Route::apiResource('inventory/products', ProductController::class);
});
```

**Layer 2: Policy-level** (fine-grained)
```php
// In FormRequest::authorize()
public function authorize(): bool
{
    return $this->user()->can('create', Product::class);
}

// In Controller (for model-specific checks)
public function update(UpdateProductRequest $request, Product $product): JsonResponse
{
    $this->authorize('update', $product); // Policy check
    ...
}
```

### RBAC Structure for Green Leaf ERP

```
Roles:
├── super-admin         ← All permissions, no restrictions
├── admin               ← Manage all modules, users, settings
├── inventory-manager   ← Full inventory access
├── inventory-staff     ← Read + limited write inventory
├── sales-manager       ← Full sales access
├── cashier             ← Create sales orders, process payments
├── purchasing-manager  ← Full purchasing access
├── accountant          ← Financial records, reports
├── hr-manager          ← HR and payroll
└── viewer              ← Read-only across modules
```

### Policy Methods (Always implement ALL)

```php
class ProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool   // index
    public function view(User $user, Product $product): bool   // show
    public function create(User $user): bool   // store
    public function update(User $user, Product $product): bool   // update
    public function delete(User $user, Product $product): bool   // destroy
    public function restore(User $user, Product $product): bool  // restore soft-deleted
    public function forceDelete(User $user, Product $product): bool  // permanent delete
}
```

### Register Policies

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Gate::policy(Product::class, ProductPolicy::class);
    Gate::policy(Order::class, OrderPolicy::class);
    // ... all models
}
```

---

## 3. INPUT VALIDATION

### Rule: Validate EVERYTHING at the FormRequest level

```php
// ✅ Correct: FormRequest validates all input
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255', 'min:2'],
            'sku'         => ['required', 'string', 'max:100', 'unique:products,sku'],
            'price'       => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ];
    }
}

// ❌ Forbidden: Validation in controller
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([...]); // WRONG — use FormRequest
}

// ❌ Forbidden: No validation
public function store(Request $request): JsonResponse
{
    Product::create($request->all()); // WRONG — mass assignment + no validation
}
```

### Validation Rules Reference

```php
// Strings
'name'     => ['required', 'string', 'min:2', 'max:255']
'email'    => ['required', 'email:rfc,dns', 'max:255']
'phone'    => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/']
'sku'      => ['required', 'string', 'alpha_dash', 'max:100']
'slug'     => ['required', 'string', 'slug', 'unique:table,slug']
'url'      => ['required', 'url', 'max:500']

// Numbers
'price'    => ['required', 'numeric', 'min:0', 'max:9999999.99']
'quantity' => ['required', 'integer', 'min:0', 'max:99999']
'percent'  => ['required', 'numeric', 'min:0', 'max:100']

// Dates
'date'     => ['required', 'date', 'after_or_equal:today']
'date_range' => ['required', 'date', 'after:start_date']

// Foreign keys
'user_id'  => ['required', 'integer', 'exists:users,id']
'ids'      => ['required', 'array', 'min:1']
'ids.*'    => ['required', 'integer', 'exists:table,id']

// Files
'image'    => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048']
'document' => ['required', 'file', 'mimes:pdf,docx', 'max:10240']

// Enums
'status'   => ['required', Rule::enum(OrderStatus::class)]
```

---

## 4. MASS ASSIGNMENT PROTECTION

```php
// ✅ Correct: Always use $fillable
class Product extends Model
{
    protected $fillable = [
        'name', 'sku', 'price', 'category_id', 'is_active',
    ];

    // Never add 'role', 'is_admin', 'balance' to fillable
}

// ❌ Forbidden: $guarded = [] (disables protection)
protected $guarded = []; // NEVER

// ❌ Forbidden: $request->all() directly
Product::create($request->all()); // NEVER
```

---

## 5. SQL INJECTION PREVENTION

```php
// ✅ Correct: Eloquent parameterized queries
$products = Product::where('name', 'like', '%' . $search . '%')->get();

// ✅ Correct: DB parameterized
DB::select('SELECT * FROM products WHERE sku = ?', [$sku]);

// ❌ Wrong: String interpolation in queries
DB::select("SELECT * FROM products WHERE sku = '{$sku}'"); // SQL INJECTION RISK
```

---

## 6. XSS PREVENTION

```php
// ✅ Blade auto-escapes {{ $var }}
{{ $product->name }}  // Safe — escaped

// ⚠️ Raw output — only for trusted HTML
{!! $trustedHtml !!}  // Only for HTML you generated (e.g., from Markdown)

// ✅ Sanitize user-provided HTML
use Illuminate\Support\HtmlString;
$safe = strip_tags($userInput);

// ✅ JSON in Blade — use @json
<div data-config='@json($config)'></div>
```

---

## 7. CSRF PROTECTION

```php
// ✅ Web routes: CSRF is automatic for POST/PUT/PATCH/DELETE
// Laravel adds VerifyCsrfToken middleware by default

// ✅ In Blade forms:
<form method="POST" action="/products">
    @csrf
    ...
</form>

// ✅ API routes: Use Sanctum (stateful or token-based)
// Stateful SPA: X-CSRF-TOKEN header
// Mobile/external: Bearer token
```

---

## 8. SECURITY HEADERS

These are applied globally via `SecureHeaders` middleware (already registered):

| Header | Value | Purpose |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `X-Frame-Options` | `DENY` | Prevent clickjacking |
| `X-XSS-Protection` | `1; mode=block` | Enable browser XSS filter |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Enforce HTTPS |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Control referrer leakage |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` | Restrict browser APIs |
| `Content-Security-Policy` | Configure per deployment | Prevent XSS (add when frontend is established) |

---

## 9. SENSITIVE DATA HANDLING

```php
// ✅ Always hash passwords
$user->password = Hash::make($request->password);

// ✅ Hide sensitive fields from API responses
class User extends Model
{
    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];
}

// ✅ Never log sensitive data
Log::info('User login attempt', [
    'email' => $email,
    // ❌ Never log: 'password' => $password
]);

// ✅ Mask in API resources
class UserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'email' => $this->email,
            // ❌ Never include: 'password', 'api_token', 'two_factor_secret'
        ];
    }
}
```

---

## 10. RATE LIMITING

```php
// routes/api.php — Rate limiting configuration
Route::middleware(['throttle:60,1'])->group(function () {
    // 60 requests per minute
});

// Stricter for auth routes
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
});

// Named limiters in AppServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

---

## 11. FILE UPLOAD SECURITY

```php
// ✅ Correct: Validate file type AND size
public function rules(): array
{
    return [
        'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
    ];
}

// ✅ Store outside public directory when possible
$path = $request->file('document')->store('documents', 'private');

// ✅ Use Spatie MediaLibrary for managed uploads
$product->addMediaFromRequest('image')
    ->toMediaCollection('product-images');

// ❌ Never: Trust file extension from client
$extension = $request->file('upload')->getClientOriginalExtension(); // UNTRUSTED

// ✅ Use MIME detection instead
$mimeType = $request->file('upload')->getMimeType(); // SAFE
```

---

## 12. AUDIT TRAIL (MANDATORY for ERP)

All models that hold business data MUST implement auditing:

```php
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Activitylog\Support\LogOptions; // v5 namespace
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model implements AuditableContract
{
    use Auditable, LogsActivity;

    // Spatie ActivityLog — logs who did what
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Owen-it Auditing — detailed field-level changes
    protected $auditExclude = ['updated_at'];
}
```

---

## 13. ENVIRONMENT SECURITY

```bash
# .env — NEVER commit this file
APP_KEY=base64:...      # Rotate if exposed
APP_DEBUG=false         # MUST be false in production
APP_ENV=production      # Set for production

# Database
DB_PASSWORD=            # Use strong, unique password

# Secret keys — must be long, random
SANCTUM_SECRET=         # If using custom secret

# Third-party — use least-privilege API keys
STRIPE_SECRET=          # Restrict to needed actions only
```

**Rules**:
- `.env` is in `.gitignore` — never commit
- Use `.env.example` for documentation (no real values)
- Rotate keys if they are ever exposed
- Use different keys for local / staging / production

---

## SECURITY SIGN-OFF CHECKLIST

Before any feature PR is merged:

- [ ] FormRequest validates all input with explicit rules
- [ ] `authorize()` method in FormRequest checks permissions
- [ ] Policy exists for every model operation
- [ ] No `$guarded = []` on models
- [ ] No `$request->all()` without going through validated
- [ ] Sensitive fields in `$hidden` on User model
- [ ] No secrets in code — all in `.env`
- [ ] File uploads validated by MIME type, not extension
- [ ] Activity logging on data-mutating operations
- [ ] Rate limiting on auth routes
- [ ] Tests cover unauthorized access attempts

---

**Owner**: Security Team / Engineering Lead
**Project**: Green Leaf ERP
**Review Cycle**: Before every sprint release
