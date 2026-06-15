# GREEN LEAF ERP — PROJECT PHASES

**Build Order for Green Leaf Traders**
**Version**: 1.0.0 | **Current Phase**: Phase 4

> Agents must build in this order. Each phase depends on the previous.
> Do NOT start Phase 2 without Phase 1 being complete and tested.

---

## PHASE OVERVIEW

| Phase | Name | Status | Priority |
|---|---|---|---|
| **Phase 1** | Foundation + Auth + User Management | ✅ COMPLETED | P0 |
| **Phase 2** | Inventory + Sorting + Wastage | ✅ COMPLETED | P0 |
| **Phase 3** | Purchase Management | ✅ COMPLETED | P0 |
| **Phase 4** | Sales Management | 🔄 IN PROGRESS | P0 |
| **Phase 5** | Finance & Accounting | ⏳ Not Started | P1 |
| **Phase 6** | Reports & Dashboard | ⏳ Not Started | P1 |
| **Phase 7** | HR & Payroll | ⏳ Not Started | P2 |
| **Phase 8** | Mobile Optimization | ⏳ Not Started | P2 |

---

## PHASE 1 — FOUNDATION + AUTH + USER MANAGEMENT

**Status**: ✅ COMPLETED
**Goal**: Secure, testable authentication with role-based access. Working login UI.

### What to Build

#### 1A — Database + Migrations
- [ ] Run existing migrations (`php artisan migrate`)
- [ ] Create `RoleSeeder` — ERP roles (super-admin, admin, inventory-manager, inventory-staff, sales-manager, cashier, purchasing-manager, accountant, hr-manager, viewer)
- [ ] Create `PermissionSeeder` — All ERP permissions by module
- [ ] Create `DemoUserSeeder` — demo accounts for testing

#### 1B — Authentication API
- [ ] `POST /api/v1/auth/login` — Sanctum token login
- [ ] `POST /api/v1/auth/logout` — Delete current token
- [ ] `GET  /api/v1/auth/me` — Get authenticated user + permissions

#### 1C — User Management API
- [ ] `GET    /api/v1/admin/users` — List users (paginated)
- [ ] `POST   /api/v1/admin/users` — Create user
- [ ] `GET    /api/v1/admin/users/{user}` — Get user
- [ ] `PUT    /api/v1/admin/users/{user}` — Update user
- [ ] `DELETE /api/v1/admin/users/{user}` — Soft delete user
- [ ] `POST   /api/v1/admin/users/{user}/roles` — Assign roles

#### 1D — Login UI (Web)
- [x] `GET /login` — Login page with demo credentials visible
- [ ] `POST /login` — Form submission handler
- [ ] `POST /logout` — Logout handler
- [ ] Auth layout (`layouts/auth.blade.php`)

#### 1E — Dashboard Shell (Web)
- [ ] Auth middleware group for web routes
- [ ] `GET /dashboard` — Protected dashboard (stub)
- [ ] App layout (`layouts/app.blade.php`)
- [ ] Sidebar navigation component

### Models Needed
- `User` (exists — extend)
- `Role` (Spatie)
- `Permission` (Spatie)

### Tests Required
- Auth: login, logout, invalid credentials, rate limiting
- User management: CRUD, role assignment, permission checks
- Authorization: each role can only access what they're allowed

### Definition of Done — Phase 1
- [x] All migrations run without error
- [x] Demo users exist (seeded)
- [x] Login/logout works via API + Web
- [x] Role-based access enforced
- [x] Login page renders on `/login` with demo credentials
- [x] All tests passing

---

## PHASE 2 — INVENTORY + SORTING + WASTAGE

**Status**: ✅ COMPLETED
**Depends On**: Phase 1 complete

### What to Build

#### 2A — Inventory Core
- Product master (vegetable catalog)
- Product categories
- Unit of measurement (kg, box, bunch)
- Supplier master (for Phase 3 prep)

#### 2B — Stock Management
- Stock ledger (current stock by product + grade)
- Stock movements (in/out/adjustment)
- Batch tracking
- Aging report

#### 2C — Sorting & Grading
- Batch sorting workflow
  - Select received batch
  - Enter grade quantities (A/B/C/Damage)
  - System validates total = received qty
  - Creates graded inventory records
- Cost allocation across grades
- Wastage recording from damage

#### 2D — Wastage Management
- Daily wastage entries
  - Product, quantity, grade, reason
  - Reasons: Rotten, Transit Damage, Expired, Unsold, Shrinkage
- Wastage cost calculation
- Wastage reports

#### 2E — Inventory API + UI
- Full CRUD for products, categories
- Stock level dashboard
- Sorting workflow UI
- Wastage entry form

### Models Needed
```
Product          (id, name, category_id, unit, is_active)
Category         (id, name, parent_id)
ProductGrade     (id, product_id, grade, label, is_active)
StockBatch       (id, product_id, received_at, total_kg, status)
StockMovement    (id, batch_id, product_id, grade, movement_type, quantity, cost_per_unit)
WastageEntry     (id, product_id, grade, quantity, reason, cost, recorded_by)
```

### Definition of Done — Phase 2
- [x] Products and categories manageable
- [x] Sorting workflow creates graded inventory correctly
- [x] Stock levels accurate after sorting
- [x] Wastage entries tracked with cost
- [x] Inventory report shows stock by grade
- [x] All tests passing

---

## PHASE 3 — PURCHASE MANAGEMENT

**Status**: ✅ COMPLETED
**Depends On**: Phase 2 (Product catalog, Inventory)

### What to Build

#### 3A — Supplier Management
- Supplier master (name, type, contact, payment terms)
- Supplier performance tracking (quality score)

#### 3B — Purchase Orders
- Create PO (supplier, date, line items with product + expected qty + price)
- PO approval workflow
- PO status: Draft → Approved → Received → Closed

#### 3C — Goods Receiving (GRN)
- Record actual received quantity per PO line
- Quantity variance tracking (ordered vs. received)
- Transport cost entry
- Labour cost entry
- Landed cost calculation per unit

#### 3D — Purchase Invoice
- Match purchase invoice to GRN
- Approve for payment
- Accounts payable entry

### Models Needed
```
Supplier          (id, name, type, contact, payment_terms)
PurchaseOrder     (id, supplier_id, po_number, status, order_date)
PurchaseOrderItem (id, po_id, product_id, quantity, unit_price)
GoodsReceived     (id, po_id, received_by, received_at, transport_cost, labour_cost)
GoodsReceivedItem (id, grn_id, po_item_id, product_id, received_qty, variance)
PurchaseInvoice   (id, grn_id, supplier_id, invoice_number, amount, status)
```

### Definition of Done — Phase 3
- [x] Suppliers manageable
- [x] POs created and approved
- [x] GRN records actual received quantities
- [x] Landed cost calculated accurately
- [x] Purchase invoice matched and payable created
- [x] All tests passing

---

## PHASE 4 — SALES MANAGEMENT

**Status**: 🔄 IN PROGRESS
**Depends On**: Phase 2 (Inventory + Grading), Phase 1 (Auth)

### What to Build

#### 4A — Customer Management
- Customer master (name, type, contact, payment terms)
- Grade preference per customer
- Credit limit management

#### 4B — Sales Orders
- Create SO (customer, date, line items with product + grade + qty + price)
- Grade-specific pricing per customer
- Stock availability check at order time
- SO status: Draft → Confirmed → Dispatched → Invoiced

#### 4C — Sales Invoice
- Invoice from confirmed SO
- Payment terms (cash/credit/30 days)
- Invoice PDF generation

#### 4D — Payment Collection
- Record payment against invoice
- Partial payment support
- Accounts receivable update

### Models Needed
```
Customer          (id, name, type, contact, payment_terms, credit_limit)
CustomerGrade     (id, customer_id, product_id, grade, price_override)
SalesOrder        (id, customer_id, so_number, status, order_date)
SalesOrderItem    (id, so_id, product_id, grade, quantity, unit_price)
SalesInvoice      (id, so_id, customer_id, invoice_number, amount, due_date, status)
Payment           (id, invoice_id, amount, payment_method, paid_at)
```

### Definition of Done — Phase 4
- [ ] Customer records with grade preferences
- [ ] Sales orders deduct from graded inventory
- [ ] Invoice generated from SO
- [ ] Payments recorded
- [ ] Accounts receivable accurate
- [ ] All tests passing

---

## PHASE 5 — FINANCE & ACCOUNTING

**Status**: ⏳ Not Started
**Depends On**: Phase 3 + Phase 4 (AP/AR sources)

### What to Build
- Chart of accounts
- General ledger
- Daily P&L calculation
- Expense tracking (non-purchase expenses)
- Bank reconciliation
- Financial reports (P&L, Balance Sheet, Cash Flow)

---

## PHASE 6 — REPORTS & DASHBOARD

**Status**: ⏳ Not Started
**Depends On**: Phases 2–5

### What to Build
- Management dashboard with KPIs
- Daily sales summary
- Daily purchase summary
- Wastage report with trends
- Inventory aging report
- Accounts receivable aging
- Product profitability report
- Supplier quality report
- Grade yield report per product

---

## PHASE 7 — HR & PAYROLL

**Status**: ⏳ Not Started
**Priority**: P2 — Build after core ERP proven

### What to Build
- Employee master
- Daily attendance
- Payroll calculation
- Salary slips
- Labour cost allocation to operations

---

## PHASE 8 — MOBILE OPTIMIZATION

**Status**: ⏳ Not Started
**Priority**: P2 — After all features stable

### What to Build
- Progressive Web App (PWA) manifest
- Warehouse mobile workflow (sorting, receiving)
- Offline support for data entry
- Barcode scanning integration

---

## AGENT BUILD RULES

1. **Always read CURRENT_SPRINT.md first** — check what's active
2. **Never start Phase N+1 before Phase N tests pass**
3. **Update CURRENT_SPRINT.md** when tasks complete
4. **Update PROJECT_STATUS.md** when a phase completes
5. **Every new model = migration + factory + seeder + repository + service + test**

---

**Owner**: Engineering Team
**Project**: Green Leaf Traders — Vegetable Trading & Distribution
