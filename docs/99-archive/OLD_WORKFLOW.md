# GREEN LEAF ERP — BUSINESS FLOW

**Document**: Core Business Operations Flow
**Version**: 1.0.0 | **Business**: Vegetable Trading & Distribution

> This document defines HOW the vegetable trading business operates.
> Every agent must understand this before building any module.

---

## THE CORE BUSINESS IN ONE SENTENCE

A vegetable trading company **buys vegetables in bulk from farmers/markets**, **sorts and grades them by quality**, and **sells to different customer types at grade-specific prices**, while tracking all costs, wastage, and revenue.

---

## COMPLETE OPERATIONAL FLOW

```
SUPPLIERS (Farmers, Markets, Agents)
    │
    │ Purchase Order
    ▼
┌─────────────────────────────────────────────┐
│  PROCUREMENT                                 │
│  - Purchase Order raised                     │
│  - Quantity, price, supplier agreed          │
│  - Transport arranged                        │
└──────────────────┬──────────────────────────┘
                   │ Goods arrive
                   ▼
┌─────────────────────────────────────────────┐
│  GOODS RECEIVING                            │
│  - Physical quantity weighed and recorded   │
│  - Variance from PO noted                   │
│  - Transport cost + labour cost added       │
│  - LANDED COST calculated                   │
│    (purchase price + freight + labour)      │
└──────────────────┬──────────────────────────┘
                   │ Raw stock in warehouse
                   ▼
┌─────────────────────────────────────────────┐
│  SORTING & GRADING                          │
│  Raw batch (e.g., 1000 kg Tomato)           │
│  sorted into:                               │
│    → Grade A: 300 kg (premium)              │
│    → Grade B: 400 kg (standard)             │
│    → Grade C: 200 kg (hotel grade)          │
│    → Damage:  100 kg (wastage)              │
│                                             │
│  Total must = received quantity (1000 kg)   │
│  Cost allocated proportionally to grades    │
└──────────────────┬──────────────────────────┘
                   │ Graded inventory created
                   ▼
┌─────────────────────────────────────────────┐
│  INVENTORY                                  │
│  Real-time stock by:                        │
│    - Product (Tomato, Spinach, Carrot...)   │
│    - Grade (A, B, C)                        │
│    - Batch/Age (Days since sorting)         │
│    - Cost per kg                            │
│                                             │
│  Aging alerts: >3 days = at-risk            │
│  Low stock alerts                           │
└──────────────────┬──────────────────────────┘
                   │ Customer requests stock
                   ▼
┌─────────────────────────────────────────────┐
│  SALES ORDER                                │
│  Customer orders specific grades:           │
│    - Priority Shop: Grade A Tomato 50 kg   │
│    - Hotel: Grade C Tomato 100 kg          │
│    - Market: Grade B Tomato 80 kg          │
│                                             │
│  Stock reserved from inventory              │
│  Grade-specific pricing applied             │
└──────────────────┬──────────────────────────┘
                   │ Order confirmed
                   ▼
┌─────────────────────────────────────────────┐
│  DELIVERY & INVOICE                         │
│  - Delivery order generated                 │
│  - Stock deducted from inventory            │
│  - Invoice created                          │
│  - Payment terms applied                    │
└──────────────────┬──────────────────────────┘
                   │ Payment cycle
                   ▼
┌─────────────────────────────────────────────┐
│  FINANCE                                    │
│  - Invoice → Accounts Receivable            │
│  - Purchase → Accounts Payable              │
│  - Cash payments → Cash book                │
│  - Daily P&L calculated                     │
│  - Expenses tracked                         │
└──────────────────┬──────────────────────────┘
                   │ End of day
                   ▼
┌─────────────────────────────────────────────┐
│  REPORTING & AUDIT                          │
│  Management sees:                           │
│    - Today's sales vs purchases             │
│    - Gross profit                           │
│    - Wastage %                              │
│    - Outstanding receivables                │
│    - Fast/slow moving items                 │
│    - Aging stock at risk                    │
└─────────────────────────────────────────────┘
```

---

## GRADING — THE CORE CONCEPT

### Why Grading Exists

A single vegetable purchase arrives as mixed quality. The business must separate this into sellable categories.

```
1000 kg Tomato arrives (cost: RM 2,000 + RM 200 transport + RM 50 labour)
Total landed cost = RM 2,250
Cost per kg = RM 2.25

After sorting:
- Grade A: 300 kg → sell @ RM 5.00/kg → Revenue: RM 1,500
- Grade B: 400 kg → sell @ RM 3.00/kg → Revenue: RM 1,200
- Grade C: 200 kg → sell @ RM 1.50/kg → Revenue: RM 300
- Damage:  100 kg → write-off          → Revenue: RM 0

Total Revenue: RM 3,000
Total Cost:    RM 2,250
Gross Profit:  RM 750 (33.3%)

Without grading visibility: business thinks they made RM 1,000 profit
Reality: RM 750 after accounting for wastage cost
```

### Grade Rules

| Grade | Quality | Typical Customer | Price vs. Base |
|---|---|---|---|
| A | Premium — no damage, perfect appearance | Priority shops, boutique grocers | +50-100% |
| B | Standard — minor marks, good quality | Regular retail shops | Base price |
| C | Economy — significant marks but edible | Hotels, canteens, bulk buyers | -20-40% |
| D | Damage | Write-off / animal feed | Zero |

---

## WASTAGE — TRACKING LOSSES

Wastage happens at multiple points. Every source must be tracked:

| Stage | Wastage Type | When Recorded |
|---|---|---|
| Sorting | Grade D / Damage | During sorting workflow |
| Storage | Expired / Rotten | Daily wastage entry |
| Delivery | Transit damage | On delivery return |
| Unsold | Aging stock written off | Manager approval |
| Shrinkage | Weight loss during storage | Inventory adjustment |

### Wastage Impact on P&L

```
Wastage is a cost, not just a quantity loss.

100 kg Tomato written off at RM 2.25/kg cost
= RM 225 direct loss
= Reduces gross profit by RM 225

Monthly wastage of 5% on RM 100,000 purchases
= RM 5,000 monthly loss
= RM 60,000 annual loss

Reducing to 3% = RM 24,000 annual savings
```

---

## CUSTOMER TYPES

| Type | Description | Grade Preference | Payment Terms |
|---|---|---|---|
| Priority Shop | Premium retail | Grade A only | Cash or 7-day credit |
| Regular Shop | Standard retail | Grade A + B | 14-day credit |
| Hotel | Kitchen / restaurant | Grade B + C | 30-day credit |
| Canteen | Mass catering | Grade C | Cash or 30-day |
| Market Vendor | Re-seller | Mixed | Cash |
| Bulk Buyer | Processor / exporter | Volume, Grade C | Negotiated |

---

## SUPPLIER TYPES

| Type | Description | Reliability |
|---|---|---|
| Direct Farmer | Fixed supply, seasonal | High for season |
| Market Agent | Aggregator, daily pricing | Medium |
| Importer | Overseas produce | High, imported |
| Co-operative | Farmer group | High, organised |

---

## DAILY OPERATIONS TIMELINE

```
5:00 AM  — Purchase team buys from market
6:00 AM  — Deliveries arrive at warehouse
7:00 AM  — Goods receiving: weigh, check, record
8:00 AM  — Sorting begins: grading
9:00 AM  — Inventory updated: grades available
9:30 AM  — Sales team takes orders
10:00 AM — Delivery trucks depart
11:00 AM — Invoices generated
12:00 PM — Cash collection (morning route)
2:00 PM  — Second delivery wave
4:00 PM  — Afternoon purchases (if needed)
5:00 PM  — Closing stock count
6:00 PM  — Wastage entry for unsold stock
7:00 PM  — Daily P&L review by management
```

---

## KEY PERFORMANCE INDICATORS (KPIs)

| KPI | Formula | Target |
|---|---|---|
| **Wastage %** | (Waste kg ÷ Purchase kg) × 100 | < 5% |
| **Gross Margin** | (Revenue − Cost) ÷ Revenue × 100 | > 25% |
| **Grade A Yield** | (Grade A kg ÷ Total sorted kg) × 100 | > 30% |
| **Receivables Age** | Average days outstanding | < 21 days |
| **Stock Turnover** | Times stock turns per week | ≥ 3× |
| **Purchase Accuracy** | (Received ÷ Ordered) × 100 | > 95% |
| **Fill Rate** | Orders fulfilled ÷ Orders placed | > 98% |

---

**Owner**: Business Analysis Team
**Project**: Green Leaf ERP — Vegetable Trading & Distribution
