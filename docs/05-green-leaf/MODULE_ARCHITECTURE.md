# Module Architecture & Domain Boundaries

This document defines the domain-driven design (DDD) boundaries for the **Green Leaf Traders** business workflow. While keeping the existing Laravel repository/service architecture, we organize our business domains inside `app/Domains/` to encapsulate logic and ensure high coherence.

---

## 🏗️ Domain-Driven Design (DDD) Mapping

```
app/Domains/
├── Shop/              # Shop identity & metadata
├── Product/           # Master catalog & SKUs
├── ShopOrder/         # Daily shop requisitions
├── Approval/          # Decision logs & late submissions
├── Procurement/       # Consolidation & supplier buying
├── Warehouse/         # Goods receiving & inspection
├── Sorting/           # Grading & shop allocation
├── Dispatch/          # Loading, route sheets & deliveries
├── Report/            # Analytical views & metrics
└── Audit/             # Activity logs & compliance
```

---

## 🛡️ Domain Responsibilities & Structure

### 1. Shop Domain
* **Responsibility**: Manages physical retail outlets. Holds shop metadata (address, contact, assigned route).
* **Communication Rules**: Mapped to `ShopOrder` (shop creates orders) and `Dispatch` (shop is delivery destination).

### 2. Product Domain
* **Responsibility**: Manages the central product catalog, SKUs, product categories, and baseline units of measurement (UOM, e.g., kg).
* **Communication Rules**: Queried by all downstream domains (Ordering, Procurement, Sorting, Warehouse). Product is a read-only dependency for other domains.

### 3. ShopOrder Domain
* **Responsibility**: Manages the daily demand/requisition sheets submitted by shops.
* **Communication Rules**: Depends on `Shop` and `Product`. Passes approved quantities to the `Procurement` domain.

### 4. Approval Domain
* **Responsibility**: Manages the approvals ledger and overrides for late shop submissions.
* **Communication Rules**: Observes `ShopOrder` transitions. Records a polymorphic history of who approved/rejected which entity.

### 5. Procurement Domain
* **Responsibility**: Aggregates approved shop demands and generates purchasing tables for suppliers.
* **Communication Rules**: Consumes approved `ShopOrder` items. Outputs `ProcurementBatch` schemas which are consumed by the `Warehouse` domain.

### 6. Warehouse Domain
* **Responsibility**: Receives physical shipments, records actual weights, and notes supplier discrepancies.
* **Communication Rules**: References `Procurement` batches. Feeds verified received weights into the `Sorting` domain.

### 7. Sorting Domain
* **Responsibility**: Manages quality grading (Grade A/B/C/Damage) and allocates physical stock to shop picking crates.
* **Communication Rules**: Takes inputs from `Warehouse` receipts. Maps allocations back to `ShopOrder` items.

### 8. Dispatch Domain
* **Responsibility**: Manages delivery routes, drivers, loading lists, and driver cash collection logs.
* **Communication Rules**: Takes inputs from `Sorting` allocations. Delivers to `Shop` locations.

### 9. Report Domain
* **Responsibility**: Compiles operational statistics into high-level dashboard metrics (reconciliation, wastage, margin).
* **Communication Rules**: Read-only observer. Queries models across all domains.

### 10. Audit Domain
* **Responsibility**: Tracks compliance, security events, and database activity logs.
* **Communication Rules**: Reuses Laravel Auditing and Spatie Activity Log packages. Runs globally.

---

## 📁 Standard Domain Folder Layout

Each domain in `app/Domains/` implements a uniform directory layout:

```
[DomainName]/
├── Models/         # Database entities
├── Services/       # Core business logic handlers
├── Repositories/   # Database access layer (extends BaseRepository)
├── Requests/       # Form input validations (extends FormRequest)
├── Policies/       # Spatie permission/gate checks
├── Actions/        # Single-responsibility actions (extends BaseAction)
├── DTOs/           # Data Transfer Objects (extends BaseDTO)
└── Enums/          # Enumerated type states
```
