# Phase 1: Shop Owner Perspective Product Requirements Document (PRD)

The Shop Owner user experience is the most critical frontend entry point in Green Leaf ERP because they interact with it **every single day**. 

The interface must be designed to feel:
> **🚀 Fast like Excel + Simple like WhatsApp ordering + Structured like ERP**

---

## 🎯 Primary Goal
A Shop Owner can log in daily, review their dashboard, and compile and submit **Tomorrow's Purchase Order** in **under 3 minutes** before the **9:30 PM** deadline.

### The 5-Second UI Verification Rule
At any moment, the interface must answer these 3 questions in **under 5 seconds**:
1. **Did I place today's order?**
2. **Was it approved?**
3. **What is coming tomorrow?**

---

## 🔄 Shop Owner User Journey

```
  Login
    │
    ▼
Dashboard (Deadline status / Tomorrow preview)
    │
    ▼
Create Tomorrow's Order (Excel-like matrix, quick templates, favorites)
    │
    ▼
Add Items & Quantities (Auto-suggest, low-qty warnings, instant search)
    │
    ▼
Save Draft / Submit (Snapshots frozen post-submission)
    │
    ▼
Track Approval (Approved, Rejected, or Partially Approved with reasons)
    │
    ▼
View Tomorrow's Delivery (Displays final approved quantities)
```

---

## 🖥️ 1. Shop Owner Dashboard Specification

### Header & Context Section
- **Welcome Banner**: Welcomes the authenticated user and displays their assigned shop name (e.g., `Welcome Back, CASIO HYPERMARKET 👋`).
- **Today's Status Card**: Displays a high-visibility badge indicating the status of the order for the upcoming cycle:
  - 🟡 `DRAFT` (Saved but not submitted)
  - 🔵 `SUBMITTED` (Pending Purchase Manager approval)
  - 🟢 `APPROVED` (Ready for warehouse consolidation)
  - 🔴 `LATE SUBMISSION` (Submitted past 9:30 PM, awaiting manager exception override)

---

### Dashboard Cards Layout

#### Card 1: Requisition Progress
- **Title**: `Tomorrow's Order (e.g., 26 May)`
- **Content**: Displays order status with a summary of items (e.g., `Status: Approved | 12 items | 110 kg`).
- **Action Button**: `View Order Details`

#### Card 2: Deadline Timer
- **Before 9:30 PM**: Displays a countdown timer indicating remaining time (e.g., `Order closes in: 02h 15m`).
- **After 9:30 PM**: Displays a critical alert badge: `🔴 Requisition Window Closed`.

#### Card 3: Tomorrow's Delivery Preview
- **Purpose**: Gives the shop owner absolute visibility into what the delivery truck is bringing tomorrow.
- **Rules**: Displays only the **Approved Quantity** (not the requested quantity), so expectations are calibrated before delivery.
- **Example list**:
  - `Tomato H ──> 15 kg`
  - `Carrot ──> 10 kg`
  - `Banana ──> 5 boxes`

#### Card 4: Activity Notifications
- Real-time updates on order transitions:
  - `✓ Requisition for 26 May has been approved`
  - `⚠ Tomato quantity adjusted: 15 kg -> 10 kg (Stock shortage)`
  - `⏰ Deadline Warning: 1 hour remaining to submit draft`

#### Card 5: Recent Requisitions History
A clean, compact historical table showing the last 7 orders:
- **Columns**: Date, Item Count, Total Quantity, Status.
- **Quick Actions**: `View`, `Copy to Tomorrow's Draft`

---

## 📝 2. Daily Purchase Order Form ("Create Tomorrow Order")

This screen replaces traditional sales order entry with an **Excel-like layout** optimized for high-speed numeric input.

---

### Screen Header & Quick Actions
- **Delivery Date**: Locked to `Tomorrow (Calculated date, e.g., 26 May)`.
- **Quick Templates**:
  - `Copy Yesterday`: Pre-populates quantities from the previous day's order.
  - `Copy Last Week Same Day`: Copies the order from the corresponding weekday last week (e.g., last Tuesday's order).
  - `Apply Favorites`: Pre-populates predefined list of frequently ordered items.

---

### Product Matrix Table
Products are grouped into clean, toggleable categories to prevent scrolling fatigue.

#### Category: Vegetables
| Product | Yesterday's Qty | Auto-Suggest | Quantity Input | Unit | Actions / Warnings |
|---|---:|---:|:---:|---|---|
| **Tomato H** | 15 kg | 15 kg | `[ 15 ]` | KG | |
| **Carrot** | 10 kg | 10 kg | `[ 10 ]` | KG | |
| **Beans** | 8 kg | 8 kg | `[ 5 ]` | KG | ⚠ *Low Qty (Usually 15kg)* |

#### Category: Fruits
| Product | Yesterday's Qty | Auto-Suggest | Quantity Input | Unit | Actions / Warnings |
|---|---:|---:|:---:|---|---|
| **Banana** | 5 boxes | 5 boxes | `[  ] ` | Box | |
| **Apple** | 3 boxes | 3 boxes | `[ 3 ]` | Box | |

#### Category: Packaged Goods
| Product | Yesterday's Qty | Auto-Suggest | Quantity Input | Unit | Actions / Warnings |
|---|---:|---:|:---:|---|---|
| **Coriander Packet** | — | — | `[ 10 ]` | Pkt | |

---

### Smart Entry Features
1. **Auto-Suggest Engine**: Suggests order quantities for each product based on the shop's rolling average of the last 7 days.
2. **Low-Quantity Warnings**: If a Shop Owner enters a quantity significantly lower than their standard order profile (e.g., entering 5 kg when they usually order 25 kg), the system displays a inline warning indicator: `Are you sure? Usually 25kg`.
3. **Fuzzy Search Filter**: A global search input at the top of the table. Typing `Tom` instantly filters categories and displays only `Tomato H` and `Tomato N`.
4. **Special Delivery Notes**: Textarea at the bottom for instructions (e.g., `"Need fresh quality for morning rush"`).

---

## 📈 3. Requisition Life-Cycle States

When viewed by the Shop Owner, the order status dictates their edit capabilities:

* **Draft**: Order is editable. Quantities can be adjusted.
* **Submitted**: Order is locked. Sent to Purchase Manager.
* **Approved**: Requisition accepted for consolidation. The order details screen shows final approved quantities matching requested quantities.
* **Partial Approval**: The Purchase Manager adjusted some item quantities down due to market supply limits. The adjusted items are flagged in orange, displaying the manager's reason (e.g., `Stock shortage`).
* **Rejected**: The entire requisition was rejected. A red alert block displays the rejection reason.

---

## 🛡️ 4. Shop Owner Access Controls

```
[Allowed Capabilities]
✔ Create and update Daily Requisition drafts.
✔ Edit draft requisitions prior to the 9:30 PM lock.
✔ View own history and copy prior orders.
✔ View manager approvals, adjustments, and reason comments.
✔ View tomorrow's expected delivery manifest.

[Disallowed Capabilities]
❌ Approve/Reject requisitions (restricted to Purchase Managers).
❌ View or search other shop orders.
❌ Edit orders once status changes to Submitted/Approved.
❌ Access consolidated procurement or supplier allocation tables.
```
