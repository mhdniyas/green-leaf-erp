# MODEL PROTOCOL

**Green Leaf ERP — Eloquent Model Standards**
**Version**: 1.0.0

> Models are data containers. They define structure, relationships, and casts.
> Business logic does NOT belong in models.

---

## WHAT A MODEL CONTAINS

```
Model = $fillable + $casts + $hidden + relationships + scopes + accessors
```

That's it. No business logic. No calculations. No service calls.

---

## CANONICAL MODEL

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Activitylog\Support\LogOptions; // v5 namespace
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Product extends Model implements AuditableContract
{
    /** @use HasFactory<ProductFactory> */
    use Auditable, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active'      => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    protected $hidden = [];

    // Activity logging — log all fillable fields, only when changed
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes — query constraints, not business logic
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeLowStock(Builder $query, int $threshold = 10): Builder
    {
        return $query->where('stock_quantity', '<=', $threshold);
    }
}
```

---

## $fillable RULES

```php
// ✅ Include: All user-editable fields
protected $fillable = [
    'name', 'sku', 'price', 'stock_quantity', 'category_id', 'is_active',
];

// ❌ Never include in $fillable:
// 'role', 'is_admin', 'balance', 'password' (unless it's the User model)
// Timestamps (handled automatically)
// Auto-calculated fields

// ❌ Never: $guarded = [] (disables protection entirely)
protected $guarded = []; // FORBIDDEN
```

---

## $casts RULES

Always cast:
- `boolean` columns (avoid 0/1 confusion)
- `decimal`/`float` for money
- `datetime` for all timestamp columns
- `json`/`array` for JSON columns
- `enum` for status fields

```php
protected $casts = [
    'price'          => 'decimal:2',
    'tax_rate'       => 'decimal:4',
    'is_active'      => 'boolean',
    'is_verified'    => 'boolean',
    'metadata'       => 'array',
    'settings'       => 'json',
    'status'         => OrderStatus::class,  // PHP Enum cast
    'shipped_at'     => 'datetime',
    'delivery_date'  => 'date',
    'created_at'     => 'datetime',
    'updated_at'     => 'datetime',
    'deleted_at'     => 'datetime',
];
```

---

## RELATIONSHIPS

### BelongsTo (child model)
```php
// Product belongsTo Category
public function category(): BelongsTo
{
    return $this->belongsTo(Category::class);
}
```

### HasMany (parent model)
```php
// Category hasMany Products
public function products(): HasMany
{
    return $this->hasMany(Product::class);
}
```

### BelongsToMany (pivot)
```php
// Order belongsToMany Products (with pivot data)
public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class, 'order_items')
        ->withPivot(['quantity', 'unit_price'])
        ->withTimestamps();
}
```

### HasOne
```php
// User hasOne Profile
public function profile(): HasOne
{
    return $this->hasOne(UserProfile::class);
}
```

### MorphMany (polymorphic)
```php
// Product has many Comments (polymorphic)
public function comments(): MorphMany
{
    return $this->morphMany(Comment::class, 'commentable');
}
```

---

## SCOPES

Scopes are query shortcuts. They are NOT business logic:

```php
// ✅ Scope: simple query constraint
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}

// Usage:
Product::active()->get();
$this->query()->active()->paginate(15);

// ✅ Scope: parameterized
public function scopeLowStock(Builder $query, int $threshold = 10): Builder
{
    return $query->where('stock_quantity', '<=', $threshold);
}

// Usage:
Product::lowStock(5)->get();
```

---

## TRAITS ON MODELS

These traits MUST be applied to all domain entity models:

```php
use Auditable;      // OwenIt\Auditing — detailed field-level change tracking
use LogsActivity;   // Spatie\Activitylog — user action tracking
use SoftDeletes;    // For models that should never be permanently deleted
use HasFactory;     // For database seeding and testing
```

---

## WHAT MODELS MUST NOT CONTAIN

```php
class Product extends Model
{
    // ❌ NO: Business logic
    public function processOrder(Order $order): void
    {
        // This belongs in OrderService
    }

    // ❌ NO: HTTP response formatting
    public function toApiArray(): array
    {
        // This belongs in ProductResource
    }

    // ❌ NO: Service calls
    public function getRecommendations(): Collection
    {
        return app(RecommendationService::class)->for($this);
        // This belongs in a service or controller
    }

    // ❌ NO: Complex calculations as methods (edge: simple derived values are OK)
    public function calculateProfit(): float
    {
        // If complex: belongs in service
        // If simple accessor: OK as getAttribute
    }
}
```

---

**Owner**: Engineering Team | **Project**: Green Leaf ERP
