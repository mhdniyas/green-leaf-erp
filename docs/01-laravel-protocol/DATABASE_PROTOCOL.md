# DATABASE PROTOCOL

**Green Leaf ERP — Database Standards**
**Version**: 1.0.0

> All database design decisions must follow these rules.
> Migration mistakes are expensive. Read this before writing a single migration.

---

## 1. MIGRATION RULES

### Always Use Artisan
```bash
php artisan make:migration create_products_table --no-interaction
php artisan make:migration add_barcode_to_products_table --no-interaction
php artisan make:migration add_index_to_products_sku --no-interaction
```

### Migration File Standards

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // 1. Primary key FIRST
            $table->id();

            // 2. Foreign keys SECOND
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // 3. Required fields
            $table->string('name');
            $table->string('sku', 100)->unique();

            // 4. Optional fields
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);

            // 5. Timestamps ALWAYS LAST before indexes
            $table->timestamps();
            $table->softDeletes();  // For domain entities

            // 6. Indexes at the end
            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

### Column Type Reference

| Data | Column Type | Notes |
|---|---|---|
| Primary key | `$table->id()` | Unsigned bigint auto-increment |
| Foreign key | `$table->foreignId('user_id')->constrained()` | Matches `users.id` |
| Short text | `$table->string('name', 255)` | VARCHAR |
| Long text | `$table->text('description')` | TEXT |
| Money/Price | `$table->decimal('price', 12, 2)` | 12 digits, 2 decimal |
| Integer count | `$table->integer('quantity')->default(0)` | INT |
| Large integer | `$table->bigInteger('views')` | BIGINT |
| Boolean flag | `$table->boolean('is_active')->default(true)` | TINYINT(1) |
| Enum/Status | `$table->string('status', 50)` | Use with Enum class |
| Date | `$table->date('delivery_date')` | DATE |
| Datetime | `$table->timestamp('shipped_at')->nullable()` | TIMESTAMP |
| JSON | `$table->json('metadata')->nullable()` | JSON |
| UUID | `$table->uuid('uuid')->unique()` | CHAR(36) |
| IP address | `$table->ipAddress('ip')` | VARCHAR(45) |

---

## 2. TABLE NAMING

### Convention
- Lowercase `snake_case`
- Plural form of model name
- Module prefix for ERP tables (optional but consistent)

```
# Standard naming
products
categories
users
orders

# Module-prefixed (recommended for ERP clarity)
inv_products            # Inventory
inv_stock_movements
sales_orders
sales_order_items
purchase_orders
purchase_order_items
acc_ledger_entries
hr_employees
hr_payroll_runs
```

### Pivot Table Naming
Alphabetical order, both singular:
```
category_product        # Category ↔ Product (many-to-many)
product_supplier        # Product ↔ Supplier
role_user               # Role ↔ User
order_product           # Order ↔ Product
```

---

## 3. INDEXING RULES

### When to Add an Index

| Condition | Add index? |
|---|---|
| Column used in `WHERE` clause | ✅ Yes |
| Column used in `ORDER BY` | ✅ Yes |
| Column used in `JOIN` condition | ✅ Yes — foreign keys |
| Column used in `LIKE '%...'` searches | ❌ No — full-text instead |
| Boolean flags queried often | ✅ Yes if combined with other conditions |
| Unique constraint | ✅ Always (unique is an index) |
| Primary key | ✅ Automatic |
| JSON columns | ❌ No (unless MySQL generated column) |

### Index Syntax

```php
// Single column
$table->index('user_id');

// Composite (column order matters — put most selective first)
$table->index(['category_id', 'is_active']);
$table->index(['user_id', 'created_at']);

// Unique constraint
$table->unique('sku');
$table->unique(['company_id', 'email']); // Unique within company

// Full-text search (MySQL)
$table->fullText(['name', 'description']);
```

---

## 4. SOFT DELETE POLICY

### Which models need soft deletes?

```
✅ SoftDeletes REQUIRED:
- products          (inventory records must be preserved)
- orders            (financial records — never hard delete)
- order_items       (line item history)
- purchase_orders
- invoices
- customers
- suppliers
- employees
- users

❌ SoftDeletes NOT needed:
- activity_log      (already append-only)
- audit_log         (append-only)
- sessions          (ephemeral)
- password_reset_tokens
- notifications     (optional — business decision)
```

### How to implement

```php
// Migration
$table->softDeletes(); // Adds deleted_at column

// Model
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
}

// Queries automatically exclude soft-deleted records
Product::all(); // excludes deleted

// Include deleted
Product::withTrashed()->get();

// Restore
$product->restore();

// Permanently delete (admin only)
$product->forceDelete();
```

---

## 5. RELATIONSHIP RULES

### Naming Conventions

```php
// BelongsTo — singular (the parent)
public function category(): BelongsTo
public function user(): BelongsTo
public function order(): BelongsTo

// HasMany — plural (the children)
public function products(): HasMany
public function orderItems(): HasMany
public function stockMovements(): HasMany

// BelongsToMany — plural
public function roles(): BelongsToMany
public function suppliers(): BelongsToMany

// HasOne — singular (the one child)
public function profile(): HasOne
public function latestMovement(): HasOne
```

### Always Use Constrained Foreign Keys

```php
// ✅ Constrained — enforces referential integrity
$table->foreignId('category_id')->constrained()->cascadeOnDelete();

// Options:
->cascadeOnDelete()     // Delete children when parent deleted
->nullOnDelete()        // Set to NULL when parent deleted
->restrictOnDelete()    // Prevent deletion if children exist (default in many DBs)
->noActionOnDelete()    // No constraint action

// ✅ Nullable foreign key
$table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

// ❌ Wrong: Uncontrolled foreign key
$table->unsignedBigInteger('category_id'); // No constraint!
```

---

## 6. MONEY / DECIMAL HANDLING

ERP deals with money. Always use `decimal`, never `float`.

```php
// ✅ Correct: decimal(12, 2) for money
$table->decimal('price', 12, 2);        // Up to 9,999,999,999.99
$table->decimal('unit_cost', 12, 4);    // 4 decimals for per-unit costs
$table->decimal('tax_rate', 5, 4);      // 0.0000 to 9.9999 (0-999%)

// ❌ Wrong: float for money (floating-point precision errors)
$table->float('price');  // NEVER for money
```

### Cast in Model

```php
protected $casts = [
    'price'     => 'decimal:2',
    'unit_cost' => 'decimal:4',
    'tax_rate'  => 'decimal:4',
];
```

---

## 7. MIGRATION CHECKLIST

Before running `php artisan migrate`:

- [ ] `up()` creates the correct structure
- [ ] `down()` reverses exactly what `up()` does
- [ ] All foreign keys use `constrained()` with appropriate action
- [ ] All domain entities have `timestamps()` and `softDeletes()`
- [ ] Money columns use `decimal` not `float`
- [ ] Indexes added for columns used in WHERE/ORDER/JOIN
- [ ] Boolean columns default to appropriate value
- [ ] Nullable set on truly optional columns only

---

**Owner**: Engineering Team | **Project**: Green Leaf ERP
