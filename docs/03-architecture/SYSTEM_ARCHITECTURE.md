# SYSTEM ARCHITECTURE

**Green Leaf Traders — System Architecture Overview**
**Version**: 1.0.0

---

## SYSTEM OVERVIEW

Green Leaf Traders is a **Laravel 13 monolith** built with clean architecture principles. It serves an agricultural business needing inventory, sales, purchasing, accounting, and HR management in one unified system.

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                             │
│  Mobile App (React Native) │ Web Dashboard (SPA) │ Admin Panel  │
└─────────────────────┬───────────────────┬─────────────────────┘
                      │                   │
                      ▼                   ▼
         REST API (JSON)           Web Routes (Blade)
         /api/v1/*                 /*

┌─────────────────────────────────────────────────────────────────┐
│                     LARAVEL APPLICATION                         │
│                                                                 │
│  ┌─────────────┐  ┌───────────┐  ┌──────────┐  ┌───────────┐  │
│  │ Controllers │  │ FormReqs  │  │ Resources│  │Middleware │  │
│  └──────┬──────┘  └───────────┘  └──────────┘  └───────────┘  │
│         │                                                       │
│  ┌──────▼──────┐  ┌───────────────────────────────────────┐    │
│  │  Services   │  │              Actions                  │    │
│  └──────┬──────┘  └───────────────────────────────────────┘    │
│         │                                                       │
│  ┌──────▼──────┐                                               │
│  │Repositories │                                               │
│  └──────┬──────┘                                               │
│         │                                                       │
│  ┌──────▼──────┐                                               │
│  │   Models    │                                               │
│  └──────┬──────┘                                               │
└─────────┼───────────────────────────────────────────────────────┘
          │
┌─────────┼───────────────────────────────────────────────────────┐
│         │           INFRASTRUCTURE LAYER                        │
│         ▼                                                       │
│  ┌─────────────┐  ┌─────────────┐  ┌────────────┐             │
│  │   MySQL DB  │  │    Redis    │  │  Storage   │             │
│  │  (Primary)  │  │(Queue+Cache)│  │  (Media)   │             │
│  └─────────────┘  └─────────────┘  └────────────┘             │
└─────────────────────────────────────────────────────────────────┘
```

---

## TECHNOLOGY STACK

| Layer | Technology | Version | Purpose |
|---|---|---|---|
| Framework | Laravel | 13.x | Application framework |
| Language | PHP | 8.4 | Backend language |
| Database | MySQL | 8.0+ | Primary data store |
| Cache | Redis | 7.x | Session, cache, rate limit |
| Queue | Redis | 7.x | Async job processing |
| Auth | Laravel Sanctum | ^4.3 | API token authentication |
| RBAC | Spatie Permission | ^7.4 | Roles and permissions |
| Audit | Spatie ActivityLog | ^5.0 | User activity tracking |
| Audit | Owen-it Auditing | ^14.0 | Model change auditing |
| Media | Spatie MediaLibrary | ^11.22 | File management |
| Excel | Maatwebsite Excel | ^3.1 | Import/export |
| Testing | PHPUnit | ^12 | Test framework |
| Styling | Laravel Pint | ^1 | Code formatter |

---

## ERP MODULES (Planned)

```
Green Leaf Traders
├── 🔐 Auth Module
│   ├── Registration / Login / Logout
│   ├── Token management
│   └── Password reset
│
├── 👥 User Management
│   ├── User CRUD
│   ├── Role assignment
│   └── Permission management
│
├── 📦 Inventory Module
│   ├── Products (with SKU, barcode, pricing)
│   ├── Categories
│   ├── Stock movements (in/out/adjustment)
│   ├── Warehouse locations
│   └── Low stock alerts
│
├── 🛒 Sales Module
│   ├── Sales orders
│   ├── Order line items
│   ├── Invoices
│   ├── Payments
│   └── Customers
│
├── 🏭 Purchasing Module
│   ├── Purchase orders
│   ├── Goods received
│   ├── Supplier management
│   └── Purchase invoices
│
├── 💰 Accounting Module
│   ├── Chart of accounts
│   ├── General ledger
│   ├── Journal entries
│   ├── Financial reports
│   └── Tax management
│
├── 👨‍💼 HR Module
│   ├── Employee records
│   ├── Attendance
│   ├── Payroll
│   └── Leave management
│
└── 📊 Reporting Module
    ├── Sales reports
    ├── Inventory reports
    ├── Financial statements
    └── Custom reports
```

---

## DATA FLOW

### API Request Flow
```
1. Request arrives at /api/v1/inventory/products
2. Sanctum middleware authenticates the token
3. ApiVersionMiddleware adds version header
4. SecureHeaders middleware adds security headers
5. Route dispatches to ProductController::index()
6. FormRequest validates input (if applicable)
7. Controller calls ProductService::paginate()
8. Service calls ProductRepository::findActive()
9. Repository runs Eloquent query → MySQL
10. Repository returns LengthAwarePaginator
11. Service returns to Controller
12. Controller wraps in ProductResource::collection()
13. ApiResponse::success() builds JSON envelope
14. Response sent to client
```

### Queue Job Flow
```
1. User action triggers job dispatch (e.g., GenerateSalesReport)
2. Job serialized and pushed to Redis queue
3. Queue worker picks up job
4. Job executes (e.g., generates PDF, sends email)
5. Job completes or fails (with retry logic)
6. Failed jobs stored in failed_jobs table
```

---

## SECURITY ARCHITECTURE

```
Request
  │
  ▼
[Rate Limiter] ← Redis-backed, keyed by IP + user
  │
  ▼
[Sanctum Auth] ← Bearer token validation
  │
  ▼
[SecureHeaders] ← HSTS, X-Frame-Options, etc.
  │
  ▼
[FormRequest::authorize()] ← Permission check
  │
  ▼
[Policy::method()] ← Fine-grained model authorization
  │
  ▼
[Business Logic] ← ActivityLog + Audit trail
```

---

## SCALABILITY PATH

**Phase 1 (Current)**: Single server monolith
```
[Load Balancer] → [Laravel App + Nginx] → [MySQL] + [Redis]
```

**Phase 2 (Growth)**: Horizontal scaling
```
[Load Balancer] → [App Server 1] ─┐
                → [App Server 2] ─┼→ [MySQL Primary] + [MySQL Replica]
                → [App Server 3] ─┘       ↓
                                   [Redis Cluster]
                                   [Queue Workers]
                                   [CDN for media]
```

**Phase 3 (Enterprise)**: Domain services
```
Extract high-load domains to separate services:
- Inventory service (stock calculations)
- Reporting service (heavy queries)
- Notification service
Keep core ERP as monolith
```

---

**Owner**: Architecture Team | **Project**: Green Leaf Traders
