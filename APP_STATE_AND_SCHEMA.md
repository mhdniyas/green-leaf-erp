# 🌿 Green Leaf Traders — Current State & Database Schema

**Last Updated**: May 25, 2026  
**Status**: ✅ Foundation Complete (v1.0.0)  
**Platform**: Laravel 13 | PHP 8.4 | MySQL | Redis

---

## 📋 TABLE OF CONTENTS

1. [Application Overview](#application-overview)
2. [Current State & Architecture](#current-state--architecture)
3. [Database Schema](#database-schema)
4. [Business Workflow](#business-workflow)
5. [Tech Stack & Packages](#tech-stack--packages)
6. [Project Structure](#project-structure)

---

## 🎯 APPLICATION OVERVIEW

### What is Green Leaf Traders?

Green Leaf is a purpose-built **Enterprise Resource Planning (ERP) system for vegetable trading and distribution businesses**. It digitizes the entire operational lifecycle from procurement to delivery, with specific support for:

- **Perishable inventory management** with age tracking
- **Quality grading** (Grade A, B, C) with grade-specific pricing
- **Wastage tracking** at every stage of operations
- **Multi-supplier procurement** with landed cost calculations
- **Customer-grade pricing matrix** (different grades → different prices)
- **Real-time financial P&L** with daily reconciliation

### Business Model

```
SUPPLIERS → PROCUREMENT → GOODS RECEIVING → SORTING & GRADING
    ↓                                              ↓
PURCHASE ORDERS                          GRADED INVENTORY
LANDED COSTS                             (A, B, C, Damage)
                                               ↓
                                      ┌─────────────────┐
                                      │ SALES ORDERS    │
                                      │ by Grade & Type │
                                      └────────┬────────┘
                                               ↓
                                      INVOICING & DELIVERY
                                               ↓
                                           PAYMENTS
                                               ↓
                                      FINANCIAL RECORDS
```

### Key Differentiators

| Challenge | Solution |
|-----------|----------|
| **Inventory Splits**: 1000 kg purchased ≠ 1000 kg sellable | Sorting & grading module converts raw stock into grade-specific inventory |
| **Price Complexity**: Grade A ≠ Grade B ≠ Grade C pricing | Customer-grade price matrix with dynamic pricing |
| **Aging Risk**: Inventory age matters for perishables | Real-time stock age tracking with alerts |
| **Wastage Blindness**: No visibility into losses | Daily wastage entry with cost tracking |
| **Financial Gaps**: No real-time P&L visibility | Dashboard with daily margin calculation |

---

## 🏗️ CURRENT STATE & ARCHITECTURE

### Phase Completion: 7/7 ✅

| Phase | Status | Details |
|-------|--------|---------|
| **1. Audit** | ✅ Complete | Infrastructure validated, Redis configured |
| **2. Enterprise Packages** | ✅ Complete | 8 packages installed (Sanctum, Spatie suite, Excel, etc.) |
| **3. Architecture** | ✅ Complete | 9 base classes, SOLID patterns, clean architecture |
| **4. Security** | ✅ Complete | Authentication, authorization, activity logging, audit trails |
| **5. API Framework** | ✅ Complete | Versioned REST API (v1), standard responses, Sanctum auth |
| **6. Directory Structure** | ✅ Complete | 28+ professional directories |
| **7. Documentation** | ✅ Complete | 8 comprehensive guides |

### Production-Ready Foundation

✅ **Authentication**: Sanctum API tokens + Session-based web auth  
✅ **Authorization**: Role-based access control (RBAC) with 100+ permissions  
✅ **Activity Logging**: Full audit trail of user actions  
✅ **Audit Trail**: Owen-it auditing on critical models  
✅ **Error Handling**: Custom exceptions with proper HTTP responses  
✅ **API Standards**: Versioned REST endpoints with consistent response format  
✅ **Security Headers**: Secure headers middleware  
✅ **Data Validation**: Form requests with business rule validation  
✅ **Code Quality**: Pint formatting + PHPUnit testing framework  

### Enterprise Packages Installed

```
✅ laravel/framework (13.x)              - Core framework
✅ laravel/sanctum (4.x)                 - API authentication
✅ spatie/laravel-permission (7.x)       - Role-based access control
✅ spatie/laravel-activitylog (5.x)      - Activity tracking
✅ owen-it/laravel-auditing (14.x)       - Audit trails
✅ spatie/laravel-backup (10.x)          - Automated backups
✅ spatie/laravel-medialibrary (11.x)    - Media management
✅ maatwebsite/excel (3.x)               - Excel import/export
```

### Directory Structure (28+)

```
app/
├── Actions/              # One-off operations with transactions
├── Services/             # Reusable business logic
├── Repositories/         # Database access layer (DDD pattern)
├── DTOs/                 # Data Transfer Objects
├── Enums/                # Type-safe enumerations
├── Helpers/              # Utility functions
├── Traits/               # Shared behaviors
├── Contracts/            # Interfaces & contracts
├── Policies/             # Authorization policies
├── Exceptions/           # Custom exceptions
├── Http/
│   ├── Controllers/Web/  # Web routes
│   ├── Controllers/Api/  # API endpoints (v1)
│   ├── Requests/         # Form validation
│   ├── Resources/        # API resources
│   └── Middleware/       # Custom middleware
├── Models/               # Eloquent models
├── Jobs/                 # Queued jobs
├── Events/               # Domain events
├── Listeners/            # Event handlers
├── Queries/              # Complex query builders
├── Domains/              # Business domains
├── Support/              # Internal support
└── ValueObjects/         # Immutable values
```

---

## 📊 DATABASE SCHEMA

### Entity Relationship Diagram

```
┌──────────────┐
│   USERS      │  (Authentication & Authorization)
│──────────────│
│ id (PK)      │
│ name         │
│ email        │◄─┐ activity_log
│ password     │  │
│ active       │  │
└──────────────┘  │
                  │
┌──────────────┐  │
│ CATEGORIES   │  │
│──────────────│  │
│ id (PK)      │  │
│ name         │  │
│ description  │  │
└────┬─────────┘  │
     │            │
     │ (1:N)      │
     ▼            │
┌──────────────┐  │
│  PRODUCTS    │◄─┘
│──────────────│
│ id (PK)      │
│ category_id  │◄─┐
│ name         │  │
│ sku          │  │ stock_movements
│ unit         │  │
│ is_active    │  │
└──────────────┘  │
                  │
┌──────────────────────┐
│  STOCK BATCHES       │
│──────────────────────│
│ id (PK)              │
│ product_id           │
│ grade                │ (A, B, C, D)
│ quantity_kg          │
│ cost_per_kg          │
│ batch_date           │
│ days_old             │
│ is_available         │
│ created_at           │◄─┐
└──────────────────────┘  │ stock_movements
     │                    │
     │ (1:N)              │
     └────────────────────┘
     
┌──────────────┐
│  SUPPLIERS   │
│──────────────│
│ id (PK)      │
│ name         │
│ type         │ (Farmer, Agent, Importer, Co-op)
│ contact      │
│ payment_terms│
│ quality_score│
└────┬─────────┘
     │
     │ (1:N)
     ▼
┌──────────────────────────┐
│  PURCHASE_ORDERS         │
│──────────────────────────│
│ id (PK)                  │
│ supplier_id              │
│ po_number                │
│ status                   │ (Draft, Confirmed, Received, Invoiced)
│ order_date               │
│ expected_delivery_date   │
│ total_amount             │
└────┬──────────────────────┘
     │ (1:N)
     ▼
┌──────────────────────────┐
│  GOODS_RECEIVEDS         │
│──────────────────────────│
│ id (PK)                  │
│ purchase_order_id        │
│ po_id (FK)               │
│ gr_number                │
│ received_date            │
│ transport_cost           │
│ labour_cost              │
│ total_landed_cost        │
│ status                   │ (Received, Sorted, Invoiced)
└────┬──────────────────────┘
     │ (1:N)
     ▼
┌──────────────────────────┐
│  GOODS_RECEIVED_ITEMS    │
│──────────────────────────│
│ id (PK)                  │
│ goods_received_id (FK)   │
│ product_id (FK)          │
│ quantity_ordered_kg      │
│ quantity_received_kg     │
│ variance_kg              │
│ cost_per_kg              │
│ received_at              │
└──────────────────────────┘


┌──────────────┐
│  CUSTOMERS   │
│──────────────│
│ id (PK)      │
│ name         │
│ type         │ (Retailer, Wholesaler, Restaurant, etc.)
│ contact      │
│ email        │
│ address      │
│ payment_terms│
│ credit_limit │
│ is_active    │
└────┬─────────┘
     │
     │ (1:N)
     ▼
┌──────────────────────────┐
│ CUSTOMER_GRADE_PRICES    │
│──────────────────────────│
│ id (PK)                  │
│ customer_id (FK)         │
│ product_id (FK)          │
│ grade                    │ (A, B, C)
│ price_per_kg             │
│ is_active                │
└──────────────────────────┘
     ▲
     │ (M:N)
     │
┌──────────────────────────┐
│  SALES_ORDERS            │
│──────────────────────────│
│ id (PK)                  │
│ customer_id (FK)         │
│ so_number                │
│ order_date               │
│ status                   │ (Draft, Confirmed, Delivered, Invoiced)
│ total_amount             │
│ payment_status           │
└────┬──────────────────────┘
     │ (1:N)
     ▼
┌──────────────────────────┐
│  SALES_ORDER_ITEMS       │
│──────────────────────────│
│ id (PK)                  │
│ sales_order_id (FK)      │
│ product_id (FK)          │
│ grade                    │
│ quantity_kg              │
│ price_per_kg             │
│ line_total               │
│ stock_batch_id           │ (Reference to source batch)
└──────────────────────────┘


┌──────────────────────────┐
│  WASTAGE_ENTRIES         │
│──────────────────────────│
│ id (PK)                  │
│ product_id (FK)          │
│ quantity_kg              │
│ cause                    │ (Rotten, Transit, Unsold, Shrinkage)
│ stage                    │ (Sorting, Storage, Delivery)
│ cost_per_kg              │
│ total_cost               │
│ notes                    │
│ recorded_at              │
│ recorded_by_id (FK)      │
└──────────────────────────┘


┌──────────────────────────┐
│  STOCK_MOVEMENTS         │
│──────────────────────────│
│ id (PK)                  │
│ product_id (FK)          │
│ stock_batch_id (FK)      │
│ movement_type            │ (In, Out, Wastage, Adjustment)
│ quantity_kg              │
│ reference_type           │ (SO, PO, Wastage, Adjustment)
│ reference_id             │
│ notes                    │
│ created_at               │
│ created_by_id (FK)       │
└──────────────────────────┘


┌──────────────────────────┐
│  PURCHASE_INVOICES       │
│──────────────────────────│
│ id (PK)                  │
│ goods_received_id (FK)   │
│ pi_number                │
│ invoice_date             │
│ due_date                 │
│ total_amount             │
│ gst_amount               │
│ final_total              │
│ status                   │ (Draft, Submitted, Paid, Overdue)
│ payment_date             │
└──────────────────────────┘


┌──────────────────────────┐
│  SALES_INVOICES          │
│──────────────────────────│
│ id (PK)                  │
│ sales_order_id (FK)      │
│ si_number                │
│ invoice_date             │
│ due_date                 │
│ total_amount             │
│ gst_amount               │
│ final_total              │
│ status                   │ (Draft, Submitted, Paid, Overdue)
│ payment_date             │
└──────────────────────────┘


┌──────────────────────────┐
│  PAYMENTS                │
│──────────────────────────│
│ id (PK)                  │
│ payable_type             │ (Invoice, Manual)
│ payable_id (FK)          │
│ payment_method           │ (Cash, Bank Transfer, Cheque)
│ amount                   │
│ payment_date             │
│ reference_number         │
│ notes                    │
│ verified_by_id (FK)      │
└──────────────────────────┘


┌──────────────────────────┐
│  ACCOUNTS                │
│──────────────────────────│
│ id (PK)                  │
│ account_code             │
│ account_name             │
│ type                     │ (Asset, Liability, Equity, Revenue, Expense)
│ sub_type                 │
│ is_active                │
└──────────────────────────┘


┌──────────────────────────┐
│  EXPENSES                │
│──────────────────────────│
│ id (PK)                  │
│ account_id (FK)          │
│ expense_category         │
│ amount                   │
│ reference                │
│ notes                    │
│ expense_date             │
│ recorded_by_id (FK)      │
└──────────────────────────┘


┌──────────────────────────┐
│  JOURNAL_ENTRIES         │
│──────────────────────────│
│ id (PK)                  │
│ je_number                │
│ je_date                  │
│ description              │
│ status                   │ (Draft, Approved, Posted)
│ total_debit              │
│ total_credit             │
│ created_by_id (FK)       │
│ approved_by_id (FK)      │
└────┬──────────────────────┘
     │ (1:N)
     ▼
┌──────────────────────────┐
│  JOURNAL_TRANSACTIONS    │
│──────────────────────────│
│ id (PK)                  │
│ journal_entry_id (FK)    │
│ account_id (FK)          │
│ debit_amount             │
│ credit_amount            │
│ line_description         │
└──────────────────────────┘


┌──────────────────────────┐
│  ACTIVITY_LOG            │
│──────────────────────────│
│ id (PK)                  │
│ log_name                 │ (default)
│ description              │
│ subject_type             │
│ subject_id               │
│ causer_type              │
│ causer_id                │
│ properties               │ (JSON: old_values, new_values, attributes)
│ created_at               │
└──────────────────────────┘
```

### Core Tables (28 Tables Total)

#### 🔐 Identity & Security
1. **users** — User accounts, authentication
2. **personal_access_tokens** — API token management (Sanctum)
3. **permission_tables** — Roles, permissions, model-has-roles, model-has-permissions (Spatie)

#### 📦 Inventory & Products
4. **categories** — Product categories (Vegetables, Fruits, etc.)
5. **products** — Product master (name, SKU, unit, active status)
6. **stock_batches** — Graded inventory batches (Grade A, B, C with age tracking)
7. **stock_movements** — Audit trail of inventory movements (In, Out, Wastage)
8. **wastage_entries** — Daily wastage tracking (quantity, cause, cost impact)

#### 🏭 Procurement Module
9. **suppliers** — Supplier master (farmer, agent, importer, co-op)
10. **purchase_orders** — PO creation with quantity & price agreed
11. **purchase_order_items** — Line items in PO (product, qty, rate)
12. **goods_receiveds** — Goods receipt document with transport & labour costs
13. **goods_received_items** — Received items with variance tracking (ordered vs. received)
14. **purchase_invoices** — Supplier invoices with payment tracking

#### 🛒 Sales Module
15. **customers** — Customer master (type, credit limit, payment terms)
16. **customer_grade_prices** — Grade-specific pricing per customer
17. **sales_orders** — Sales orders (customer, product, grade, quantity)
18. **sales_order_items** — Line items with grade reference & batch tracking
19. **sales_invoices** — Customer invoices (linked to sales orders)

#### 💰 Finance & Accounting
20. **payments** — Payment records (linked to invoices, cash/bank transfers)
21. **accounts** — Chart of accounts (Asset, Liability, Equity, Revenue, Expense)
22. **expenses** — Operational expenses (transport, labour, utilities, etc.)
23. **journal_entries** — Accounting entries (draft/approved/posted)
24. **journal_transactions** — Debit/credit lines in journal entries

#### 📊 Monitoring & Audit
25. **activity_log** — User activity tracking (Spatie)
26. **audits** — Model change auditing (Owen-it)
27. **cache** — Laravel cache table
28. **jobs** — Queued jobs table

---

## 🔄 BUSINESS WORKFLOW

### Daily Operations Timeline

```
5:00 AM  ┌─ Purchase team buys from farmers/markets
         │
6:00 AM  ├─ Deliveries arrive at warehouse
         │  
7:00 AM  ├─ GOODS RECEIVING
         │  ├─ Weigh physical stock
         │  ├─ Check variance from PO
         │  ├─ Record transport cost (freight)
         │  ├─ Record labour cost
         │  └─ Calculate LANDED COST (PO price + freight + labour)
         │
8:00 AM  ├─ SORTING & GRADING (if applicable)
         │  ├─ Raw batch splits into grades
         │  ├─ Record Grade A, B, C quantities
         │  ├─ Record wastage (damage, rotten)
         │  └─ Cost allocated to each grade
         │
9:00 AM  ├─ INVENTORY UPDATED
         │  ├─ Stock batches created by grade
         │  ├─ Real-time quantity available
         │  └─ Age tracking begins
         │
9:30 AM  ├─ SALES TEAM TAKES ORDERS
         │  ├─ Customer calls with requirements
         │  ├─ Check stock availability by grade
         │  ├─ Apply customer-grade pricing
         │  └─ Create Sales Order (SO)
         │
10:00 AM ├─ DELIVERY TRUCKS DEPART
         │  ├─ Pack goods per sales order
         │  ├─ Deduct from stock_batches
         │  ├─ Record stock movement
         │  └─ Update inventory quantities
         │
11:00 AM ├─ INVOICING
         │  ├─ Generate Sales Invoice (SI)
         │  ├─ Attach to Sales Order
         │  └─ Send to customer
         │
12:00 PM ├─ CASH COLLECTION (Morning Route)
         │  ├─ Receive payments
         │  ├─ Record in Payments table
         │  └─ Update invoice status to Paid
         │
2:00 PM  ├─ SECOND DELIVERY WAVE
         │  └─ Repeat: SO → Deduct → Invoice → Payment
         │
4:00 PM  ├─ AFTERNOON PURCHASES (if needed)
         │  └─ Create additional Purchase Orders
         │
5:00 PM  ├─ CLOSING STOCK COUNT
         │  ├─ Physical count of remaining stock
         │  ├─ Compare to system (find shrinkage)
         │  └─ Adjust if variance
         │
6:00 PM  ├─ WASTAGE ENTRY
         │  ├─ Record any unsold stock wastage
         │  ├─ Record reason (rotten, unsaleable, etc.)
         │  ├─ Record cost per kg
         │  └─ Calculate total cost impact
         │
7:00 PM  └─ DAILY P&L REVIEW
            ├─ Calculate daily revenue (SI total)
            ├─ Calculate daily COGS (purchased inventory cost)
            ├─ Calculate wastage impact
            ├─ Calculate gross profit
            ├─ Calculate net margin %
            └─ Send to management dashboard
```

### Procurement Workflow (Detailed)

```
┌──────────────────────────────────────────────────────────┐
│ STEP 1: CREATE PURCHASE ORDER                           │
│ - Purchase manager decides to buy Tomato from Farmer    │
│ - Creates PO: supplier, product, qty (1000 kg), rate    │
│ - Status: Draft                                         │
│ - Expected delivery: Next day                           │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 2: CONFIRM & SEND PO                               │
│ - PO confirmed with supplier                            │
│ - Status: Confirmed                                     │
│ - Supplier acknowledges delivery date & terms           │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 3: GOODS ARRIVE & RECEIVING                        │
│ - Physical goods arrive at warehouse                    │
│ - Weigh goods: Actual received = 980 kg (variance: -20) │
│ - Record transport invoice: RM 100                      │
│ - Record labour: RM 50                                  │
│                                                         │
│ LANDED COST CALCULATION:                                │
│ PO Amount: 1000 kg × RM 2.00 = RM 2,000                │
│ Transport: RM 100                                       │
│ Labour: RM 50                                           │
│ TOTAL LANDED COST: RM 2,150 for 980 kg                 │
│ Cost per kg: RM 2.19                                    │
│                                                         │
│ Status: Received                                        │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 4: SORTING & GRADING (if applicable)               │
│ Raw 980 kg sorted into:                                 │
│  - Grade A: 290 kg @ RM 2.19 = RM 635 cost             │
│  - Grade B: 400 kg @ RM 2.19 = RM 876 cost             │
│  - Grade C: 180 kg @ RM 2.19 = RM 395 cost             │
│  - Damage:  110 kg @ RM 2.19 = RM 241 cost (wastage)   │
│                                                         │
│ Stock batches created: 3 sellable + 1 wastage           │
│ Status: Sorted                                          │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 5: INVENTORY READY FOR SALES                       │
│ - Goods Received now shows: 980 kg received, 870 kg     │
│   sellable, 110 kg wastage                              │
│ - Status: Invoiced (after supplier invoice processed)   │
│                                                         │
│ Real inventory now shows:                               │
│ ├─ Tomato Grade A: 290 kg @ RM 2.19/kg                │
│ ├─ Tomato Grade B: 400 kg @ RM 2.19/kg                │
│ ├─ Tomato Grade C: 180 kg @ RM 2.19/kg                │
│ └─ Wastage Tracked: 110 kg, Cost: RM 241               │
└──────────────────────────────────────────────────────────┘
```

### Sales Workflow (Detailed)

```
┌──────────────────────────────────────────────────────────┐
│ STEP 1: CUSTOMER ORDER RECEIVED                         │
│ - Priority Shop calls: "Need 50 kg Grade A Tomato"      │
│ - Sales manager checks inventory:                       │
│   ✓ Grade A Tomato: 290 kg available                    │
│ - Get price for Priority Shop + Grade A Tomato          │
│   → Customer Grade Price: RM 4.50/kg                    │
│ - Create Sales Order (SO):                              │
│   ├─ Customer: Priority Shop                            │
│   ├─ Product: Tomato                                    │
│   ├─ Grade: A                                           │
│   ├─ Qty: 50 kg                                         │
│   ├─ Price: RM 4.50/kg                                  │
│   ├─ Total: RM 225                                      │
│   └─ Status: Draft                                      │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 2: CONFIRM & RESERVE STOCK                         │
│ - SO confirmed with customer                            │
│ - Stock reserved from Grade A batch:                    │
│   • From: Stock Batch (Tomato Grade A, 290 kg)          │
│   • Reserve: 50 kg                                      │
│   • Remaining: 240 kg                                   │
│ - Status: Confirmed                                     │
│ - Stock movement created: Out (from batch to SO)        │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 3: PACK & DELIVER                                  │
│ - Warehouse packs 50 kg Grade A Tomato                  │
│ - Update stock_batches: Tomato Grade A = 240 kg now    │
│ - Delivery truck departs                                │
│ - Status: Delivered                                     │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 4: GENERATE INVOICE                                │
│ - Sales Invoice created:                                │
│   ├─ SI Number: SI-2026-05-25-001                       │
│   ├─ Customer: Priority Shop                            │
│   ├─ Items: 50 kg Grade A Tomato @ RM 4.50 = RM 225   │
│   ├─ GST (6%): RM 13.50                                 │
│   ├─ Total: RM 238.50                                   │
│   ├─ Due Date: Net 7 (unless customer is COD)          │
│   └─ Status: Submitted                                  │
│                                                         │
│ Journal Entry Created (Auto):                           │
│ Dr. Accounts Receivable    RM 238.50                    │
│   Cr. Revenue               RM 225.00                    │
│   Cr. GST Payable           RM 13.50                     │
└──────────────────────────────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────┐
│ STEP 5: PAYMENT RECEIVED & RECONCILIATION                │
│ - Customer pays RM 238.50 (cash or bank transfer)       │
│ - Cashier records Payment:                              │
│   ├─ Payment Method: Cash                               │
│   ├─ Amount: RM 238.50                                  │
│   ├─ Date: 2026-05-25                                   │
│   └─ Status: Verified                                   │
│                                                         │
│ Sales Invoice Status Updated: Paid                      │
│                                                         │
│ Journal Entry Created (Auto):                           │
│ Dr. Cash                   RM 238.50                    │
│   Cr. Accounts Receivable   RM 238.50                    │
└──────────────────────────────────────────────────────────┘
```

### Grading Workflow

```
RAW STOCK ARRIVES
  (1000 kg Tomato)
       │
       ▼
┌───────────────────────────────┐
│ SORTING & QUALITY CHECK        │
│ - Inspect each batch          │
│ - Identify defects            │
│ - Measure size (if applicable) │
└───────────────┬─────────────────┘
                │
    ┌───────────┼───────────┬──────────┐
    ▼           ▼           ▼          ▼
┌─────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│Grade A  │ │ Grade B  │ │ Grade C  │ │ Damage   │
│Perfect  │ │ Standard │ │ Hotel    │ │ Rotten   │
│300 kg   │ │ 400 kg   │ │ 200 kg   │ │ 100 kg   │
│RM 4.50/ │ │ RM 3.00/ │ │ RM 1.50/ │ │ RM 0.00/ │
│kg       │ │ kg (base)│ │ kg       │ │ kg       │
└────┬────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘
     │           │            │            │
     └─────────┬─┴────────────┬┴────────────┘
               │              │
               ▼              ▼
         ┌──────────────┐  ┌──────────────┐
         │ Stock Batch  │  │ Wastage Cost │
         │ (Sellable)   │  │ RM 241       │
         └──────────────┘  └──────────────┘
```

### Financial Flow

```
PURCHASE                          SALES
─────────                          ─────
Purchase Order Created     →    Sales Order Created
├─ Supplier                 ├─ Customer
├─ Product + Qty            ├─ Grade
├─ Rate                      └─ Qty
└─ Total Amount                

         │                            │
         ▼                            ▼
    Goods Received          Sales Invoice
    ├─ Weight & Record      ├─ Amount
    ├─ Transport Cost       ├─ GST
    ├─ Labour Cost          └─ Total
    └─ Landed Cost Cal.

         │                            │
         ▼                            ▼
    Purchase Invoice        Accounts Receivable
    ├─ Amount               (DR)
    ├─ GST                  
    └─ Total                    │
         │                      ▼
         ▼              PAYMENT RECEIVED
   Accounts Payable      ├─ Cash In
   (CR)                  ├─ Bank Transfer
         │               └─ Cheque
         │
         ▼
    PAYMENT MADE
    ├─ Cash Out
    └─ Bank Transfer

         │                            │
         └─────────────┬──────────────┘
                       ▼
            ┌──────────────────────┐
            │ DAILY P&L CALCULATION│
            │                      │
            │ Revenue:    RM 3,000 │
            │ - COGS:    -RM 2,250 │
            │ - Wastage: -RM 241   │
            │ ────────────────────  │
            │ Gross P/L: RM 509    │
            │ Margin %:  17%       │
            └──────────────────────┘
```

### Key Metrics & KPIs

| Metric | Formula | Target | Importance |
|--------|---------|--------|-----------|
| **Wastage %** | (Waste kg ÷ Purchase kg) × 100 | < 5% | Critical for profitability |
| **Gross Margin** | (Revenue − Cost) ÷ Revenue × 100 | > 25% | Business health |
| **Grade A Yield** | (Grade A kg ÷ Total sorted) × 100 | > 30% | Quality indicator |
| **Stock Turnover** | Times stock turns per week | ≥ 3× | Warehouse efficiency |
| **Receivables Age** | Average days outstanding | < 21 days | Cash flow health |
| **Fill Rate** | Orders fulfilled ÷ Orders placed | > 98% | Customer satisfaction |
| **Purchase Accuracy** | (Received ÷ Ordered) × 100 | > 95% | Supplier reliability |

---

## 🛠️ TECH STACK & PACKAGES

### Core Framework
- **Laravel** 13.x — Web application framework
- **PHP** 8.4 — Runtime environment
- **MySQL** — Relational database
- **Redis** — Cache & session store
- **Node.js + npm** — Frontend tooling

### Authentication & Authorization
- **laravel/sanctum** (4.x) — API token-based auth
- **spatie/laravel-permission** (7.x) — Role-based access control (RBAC)

### Activity & Audit
- **spatie/laravel-activitylog** (5.x) — User action tracking
- **owen-it/laravel-auditing** (14.x) — Model change auditing

### Infrastructure & Operations
- **spatie/laravel-backup** (10.x) — Automated backups
- **spatie/laravel-medialibrary** (11.x) — Media file management
- **maatwebsite/excel** (3.x) — Excel import/export

### Frontend Styling
- **tailwindcss** (4.x) — Utility-first CSS framework

### Testing & Quality
- **phpunit/phpunit** (12.x) — Unit & feature testing
- **laravel/pint** (1.x) — Code style formatter

### Developer Tools
- **laravel/pail** (1.x) — Log viewer
- **barryvdh/laravel-ide-helper** (3.7) — IDE intellisense
- **laravel/telescope** (5.x) — Debugging (dev only)

### Validation & Business Rules
- **Form Requests** — Per-endpoint validation
- **Policies** — Model-level authorization

---

## 📂 PROJECT STRUCTURE

### Root Files
```
/
├── AGENTS.md                 # Agent guidelines & Laravel Boost info
├── FOUNDATION_COMPLETE.md    # Setup status & phase completion
├── README.md                 # Laravel welcome (generic)
├── artisan                   # Laravel CLI
├── composer.json             # PHP dependencies
├── package.json              # Node dependencies (Tailwind, Vite)
├── phpunit.xml               # Testing configuration
├── vite.config.js            # Frontend build config
├── boost.json                # Laravel Boost configuration
└── APP_STATE_AND_SCHEMA.md   # This file
```

### Application Directories

**app/** — Application code (13 subdirectories)
- **Actions/** — One-off operations with business logic
- **Services/** — Reusable business services
- **Repositories/** — Database access layer
- **DTOs/** — Data Transfer Objects
- **Enums/** — Type-safe enumerations
- **Models/** — Eloquent models
- **Http/** — Controllers, requests, resources, middleware
- **Policies/** — Authorization policies
- **Exceptions/** — Custom exceptions
- **Traits/** — Shared behaviors
- **Contracts/** — Interfaces
- **Helpers/** — Utility functions
- **Support/** — Internal support classes
- **ValueObjects/** — Immutable values
- **Queries/** — Complex query builders
- **Domains/** — Business domains (future)
- **Jobs/** — Queued jobs
- **Events/** — Domain events
- **Listeners/** — Event handlers

**routes/** — Application routing
- **web.php** — Web routes (sessions, cookies)
- **api.php** — API routes (v1, Sanctum auth)
- **console.php** — Artisan commands

**database/** — Database setup
- **migrations/** — Schema creation (28 migrations)
- **factories/** — Eloquent factories for testing
- **seeders/** — Database seeding

**tests/** — Automated testing
- **Feature/** — Feature tests (workflow tests)
- **Unit/** — Unit tests
- **Integration/** — Integration tests
- **Architecture/** — Architecture rules tests
- **Security/** — Security tests

**resources/** — Frontend assets
- **views/** — Blade templates
- **css/** — Tailwind styles
- **js/** — JavaScript (Vite)

**docs/** — Project documentation
- **00-operating-system/** — System overview & decisions
- **05-green-leaf/** — Business requirements & flow
- **01-laravel-protocol/** — Laravel setup docs
- **02-security/** — Security framework
- **05-security/** — Security practices
- **06-api/** — API documentation

**storage/** — Runtime storage
- **app/** — Application files
- **framework/** — Cache & sessions
- **logs/** — Application logs

**public/** — Web-accessible files
- **index.php** — Entry point
- **build/** — Compiled frontend assets

---

## 📈 Current Development Status

### ✅ Completed Features
- [x] Enterprise foundation setup (7 phases)
- [x] Database schema (28 tables)
- [x] Authentication & authorization (Sanctum + RBAC)
- [x] API framework with versioning
- [x] Activity logging & audit trails
- [x] Base classes for SOLID architecture
- [x] Directory structure (28+ directories)
- [x] Testing framework (PHPUnit)

### 🔄 In Progress
- [ ] Core modules testing
- [ ] API endpoint development
- [ ] UI/UX implementation (Blade templates)
- [ ] Business logic integration tests

### 📋 Planned Features
- [ ] Dashboard & reporting
- [ ] Advanced analytics
- [ ] Mobile app support
- [ ] Export/import capabilities
- [ ] Multi-tenant support (future)

---

## 🚀 Next Steps

1. **Develop Core Workflows**
   - Procurement module UI & API
   - Sales module UI & API
   - Grading workflow implementation

2. **Implement Business Logic**
   - Landed cost calculation
   - Grade-specific pricing matrix
   - Wastage tracking

3. **Build Dashboard & Reports**
   - Daily P&L dashboard
   - Stock aging report
   - Wastage analysis

4. **Testing & QA**
   - Feature tests for all workflows
   - Performance optimization
   - Security penetration testing

---

**Document**: APP_STATE_AND_SCHEMA.md  
**Last Updated**: May 25, 2026  
**Version**: 1.0.0
