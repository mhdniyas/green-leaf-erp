# GREEN LEAF ERP — PRODUCT REQUIREMENTS DOCUMENT

**Version**: 1.0
**Project Type**: Vegetable Trading & Distribution ERP
**Platform**: Web Application (Laravel 13)
**Last Updated**: 2026-05-22

---

## 1. EXECUTIVE SUMMARY

Green Leaf ERP is a purpose-built enterprise resource planning system for vegetable trading and distribution businesses. It digitizes the entire operational lifecycle — from procurement to delivery — with specific support for the complexities of perishable, gradeable inventory.

Unlike off-the-shelf ERP solutions, Green Leaf is designed around the reality that:
- **1000 kg of tomatoes purchased ≠ 1000 kg of sellable inventory** (damage, grade splits)
- **Grade A tomatoes ≠ Grade C tomatoes** (different price, different customer)
- **Inventory age matters** — what's fresh today is waste tomorrow
- **Every kg lost to wastage is a direct profit hit**

---

## 2. TARGET USERS

| Role | Primary Responsibility | Key Needs |
|---|---|---|
| **Business Owner / Management** | Oversight, profitability | Dashboard, P&L, reports |
| **Purchase Manager** | Procure from farmers/markets | PO creation, cost tracking |
| **Warehouse Staff** | Receiving, sorting, storage | Grading, stock entry |
| **Sales Manager** | Customer orders, pricing | Order management, invoices |
| **Cashier** | Payments, cash collection | Sales entry, receipts |
| **Accountant** | Ledger, reconciliation | Financial records, reports |
| **Delivery Staff** | Order fulfillment | Delivery orders |

---

## 3. BUSINESS PROBLEMS SOLVED

### 3.1 Purchase Visibility Gap

**Problem**: Businesses buy vegetables daily from multiple sources but cannot track:
- Actual received quantity vs. ordered quantity
- Transport and labour costs per purchase
- Final landed cost per kg

**Solution**: Purchase module with:
- Purchase Orders with quantity variance tracking
- Landed cost calculator (purchase + transport + labour = true cost)
- Per-purchase profit margin visibility

### 3.2 Inventory Complexity (Grading)

**Problem**: 1000 kg of tomatoes purchased splits into multiple grades after sorting:

```
Purchase: 1000 kg Tomato @ RM 2.00/kg (RM 2,000)
After sorting:
  → Grade A: 300 kg (sellable @ RM 4.00/kg)
  → Grade B: 400 kg (sellable @ RM 2.50/kg)
  → Grade C: 200 kg (hotel grade @ RM 1.50/kg)
  → Damage:  100 kg (write-off)

Expected Revenue: (300×4) + (400×2.5) + (200×1.5) = RM 2,500
Actual Cost: RM 2,000 + transport + labour
Profit: RM 500 − expenses
```

**Solution**: Sorting & Category Management module that converts raw purchase stock into graded sellable inventory.

### 3.3 Wastage Tracking

**Problem**: Businesses cannot identify:
- Daily wastage percentage per product
- Which supplier delivers poor quality
- Which products lose money consistently

**Solution**: Wastage module with:
- Daily wastage entries (cause: rotten, transit damage, unsold)
- Wastage % per product and per supplier
- Cost of wastage tracking

### 3.4 Sales Complexity

**Problem**: Different customers receive different grades at different prices:
- Priority shops → Grade A only
- Hotels → Grade B/C
- Bulk buyers → Mixed grade

**Solution**: Customer-grade pricing matrix with sales orders tied to specific inventory grades.

### 3.5 Financial Blind Spots

**Problem**: Business owners don't know daily P&L, outstanding receivables, or cash position.

**Solution**: Real-time financial dashboard with daily P&L, aging receivables, and cash flow.

---

## 4. CORE MODULES

### Module 1: Authentication & User Management
- Login / logout with Sanctum tokens
- Role-based access control (RBAC)
- User profiles and audit trails

### Module 2: Inventory Management
- Product master (name, unit, category)
- Inventory tracking by: product, grade, batch, age
- Real-time stock levels
- Low stock alerts

### Module 3: Purchase Management
- Supplier master
- Purchase orders
- Goods receiving (with quantity variance)
- Landed cost calculation
- Purchase invoice matching

### Module 4: Sorting & Grading
- Batch sorting workflow
- Grade assignment (A, B, C, Damage)
- Automatic inventory creation from sorted batches
- Wastage recording at sorting stage

### Module 5: Sales Management
- Customer master (with grade preferences)
- Sales orders with grade-specific pricing
- Sales invoices
- Delivery management
- Payment collection

### Module 6: Wastage Management
- Daily wastage recording
- Wastage cause tracking (rotten, damage, expired, unsold)
- Wastage cost attribution
- Wastage reports by product/supplier/period

### Module 7: Finance & Accounting
- Chart of accounts
- General ledger
- Accounts receivable (customer balances)
- Accounts payable (supplier balances)
- Expense tracking
- Daily P&L
- Cash flow

### Module 8: HR & Payroll
- Employee master
- Attendance (daily/weekly)
- Payroll processing
- Salary slips

### Module 9: Reports & Dashboard
- Management dashboard (daily KPIs)
- Sales reports
- Purchase reports
- Inventory reports
- Wastage reports
- Financial statements
- Fast-moving / slow-moving inventory
- Aging stock report

---

## 5. KEY OPERATIONAL FLOWS

### Flow 1: Daily Purchase Cycle
```
Farmer/Market → Purchase Order → Goods Receiving → Sorting
→ Grade A/B/C Inventory Created → Wastage Recorded
→ Cost Split Across Grades → Inventory Available for Sale
```

### Flow 2: Daily Sales Cycle
```
Customer Order → Check Grade Availability → Confirm Stock
→ Sales Order Created → Picked from Inventory → Delivery
→ Invoice Generated → Payment Collected → Ledger Updated
```

### Flow 3: Financial Reconciliation
```
Daily: Purchases + Expenses logged
Daily: Sales + Payments logged
Weekly: Payables/Receivables reconciled
Monthly: P&L report generated
Monthly: Supplier/Customer statements sent
```

---

## 6. GRADING SYSTEM

This is the most critical feature of Green Leaf ERP.

### Default Grades (configurable per business)

| Grade | Label | Description | Typical Price Premium |
|---|---|---|---|
| **A** | Premium | Best quality, minimal blemish | Highest (+40-60%) |
| **B** | Standard | Good quality, minor marks | Base price |
| **C** | Economy | Hotel/bulk grade | Discounted (-20-30%) |
| **D** | Damage | Unsellable, write-off | Zero value |

### Sorting Workflow
1. Purchase batch received (e.g., 500 kg Spinach)
2. Warehouse team grades each batch
3. System records: X kg Grade A, Y kg Grade B, Z kg Grade C, W kg Damage
4. X + Y + Z + W must equal 500 kg (validated)
5. Landed cost distributed across grades (using configurable cost allocation)
6. Each grade becomes a separate inventory record
7. Damage recorded as wastage with cost

---

## 7. NON-FUNCTIONAL REQUIREMENTS

| Requirement | Specification |
|---|---|
| **Availability** | 99.9% uptime during business hours (6am–10pm) |
| **Performance** | Dashboard loads < 2 seconds |
| **Security** | Role-based, all data encrypted in transit |
| **Mobile** | Responsive web (tablet/phone for warehouse staff) |
| **Audit** | All data changes are audited with user + timestamp |
| **Backup** | Automated daily database backup |
| **Concurrency** | Support 20+ simultaneous users |

---

## 8. PHASE DELIVERY PLAN

| Phase | Scope | Priority |
|---|---|---|
| **Phase 1** | Foundation + Authentication + User Management | P0 — Must Have |
| **Phase 2** | Inventory + Sorting + Grading + Wastage | P0 — Must Have |
| **Phase 3** | Purchase Management | P0 — Must Have |
| **Phase 4** | Sales Management + Invoicing | P0 — Must Have |
| **Phase 5** | Finance & Accounting | P1 — High Value |
| **Phase 6** | HR & Payroll | P2 — Future |
| **Phase 7** | Reports & Dashboard | P1 — High Value |
| **Phase 8** | Mobile Optimization | P2 — Future |

---

## 9. SUCCESS METRICS

| Metric | Target |
|---|---|
| Daily purchase entries | 100% captured (vs. paper/excel) |
| Inventory accuracy | ≥ 95% match to physical count |
| Wastage reduction | 15-20% reduction within 3 months |
| Accounts receivable | < 30 days average age |
| P&L visibility | Daily (vs. monthly guesswork) |
| User adoption | All staff using within 30 days of launch |

---

**Document Owner**: Engineering + Product Team
**Client**: Green Leaf Trading & Distribution Sdn Bhd
**Review**: Each phase delivery
