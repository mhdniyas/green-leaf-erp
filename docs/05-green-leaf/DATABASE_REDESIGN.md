# Database Redesign Specification

This document details the target relational database schema for the **Green Leaf ERP** workflow pivot. No migrations are created in this architectural phase. This document serves as the design specification.

---

## 🗺️ Entity Relationship Overview

```
[shops] 1 ──── N [shop_orders] 1 ──── N [shop_order_items]
                        │                       │
                        │ 1                     │ 1
                        ▼                       ▼
                  [approvals]             [sorting_allocations] ◄──── 1 [warehouse_receipts]
                                                │                                 │
                                                ▼ 1                               ▼ 1
                                           [dispatches] 1 ──────────────── N [delivery_logs]
```

---

## 📊 Database Schema Definitions

### 1. `shops`
* **Purpose**: Master table for physical shop outlets.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `code` (varchar(20), unique, index) - Unique short code for the shop (e.g., `SHOP_001`)
  - `name` (varchar(100)) - Full display name
  - `status` (varchar(20)) - `active`, `inactive`
  - `address` (text) - Physical address for delivery routing
  - `contact_name` (varchar(100)) - Primary contact person
  - `contact_phone` (varchar(20)) - Primary contact phone
  - `route_id` (bigint, unsigned, nullable, index) - Logical link to routes
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 2. `shop_orders`
* **Purpose**: Order headers capturing daily shop requisitions.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `shop_id` (bigint, unsigned, FK $\rightarrow$ `shops.id`)
  - `business_date` (date, index) - Operational date for which stock is needed
  - `order_number` (varchar(50), unique, index) - Auto-generated unique number (e.g., `ORD-20260525-001`)
  - `state` (varchar(30), index) - State machine status (e.g., `draft`, `submitted`, `approved`, `rejected`)
  - `submission_type` (varchar(20)) - `on_time`, `late`
  - `submitted_at` (timestamp, nullable) - Timestamp of Shop Owner click
  - `deadline_at` (timestamp) - Reference cutoff point
  - `approval_required` (boolean, default true)
  - `approved_at` (timestamp, nullable)
  - `rejected_at` (timestamp, nullable)
  - `created_by` (bigint, unsigned, FK $\rightarrow$ `users.id`)
  - `updated_by` (bigint, unsigned, FK $\rightarrow$ `users.id`, nullable)
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 3. `shop_order_items`
* **Purpose**: Line items representing specific product demands under a shop order.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `shop_order_id` (bigint, unsigned, FK $\rightarrow$ `shop_orders.id` ON DELETE CASCADE)
  - `product_id` (bigint, unsigned, FK $\rightarrow$ `products.id`)
  - `requested_qty` (decimal(10,2)) - Initial quantity requested by the shop owner
  - `approved_qty` (decimal(10,2), nullable) - Final approved quantity calibrated by the manager
  - `unit` (varchar(20)) - Unit of measurement snapshot (e.g., `kg`)
  - `notes` (text, nullable)
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 4. `approvals`
* **Purpose**: Polymorphic history of workflow approvals and manager override decisions.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `approvable_type` (varchar(100), index) - Polymorphic model type (e.g., `App\Models\ShopOrder`)
  - `approvable_id` (bigint, unsigned, index) - Polymorphic model ID
  - `stage` (varchar(50)) - Stage label (e.g., `initial_approval`, `late_override`)
  - `decision` (varchar(30)) - `approved`, `rejected`
  - `comment` (text, nullable) - Mandatory for rejections, optional for approvals
  - `decided_by` (bigint, unsigned, FK $\rightarrow$ `users.id`)
  - `decided_at` (timestamp)
  - `metadata_json` (json, nullable) - Holds override reasons or parameters
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 5. `procurement_batches`
* **Purpose**: Purchasing batches aggregating approved daily demands.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `batch_number` (varchar(50), unique, index) - Auto-generated batch code (e.g., `PROC-20260525-001`)
  - `business_date` (date, unique, index) - Target date of delivery to warehouse
  - `state` (varchar(30), index) - State machine status (e.g., `draft`, `generated`, `closed`)
  - `generated_at` (timestamp)
  - `generated_by` (bigint, unsigned, FK $\rightarrow$ `users.id`)
  - `notes` (text, nullable)
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 6. `procurement_items`
* **Purpose**: Consolidated product-level purchasing requirements within a procurement batch.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `procurement_batch_id` (bigint, unsigned, FK $\rightarrow$ `procurement_batches.id` ON DELETE CASCADE)
  - `product_id` (bigint, unsigned, FK $\rightarrow$ `products.id`)
  - `supplier_id` (bigint, unsigned, nullable, FK $\rightarrow$ `suppliers.id`)
  - `total_required_qty` (decimal(10,2)) - Consolidated sum of all approved shop orders
  - `procured_qty` (decimal(10,2), default 0) - Amount confirmed with supplier
  - `received_qty` (decimal(10,2), default 0) - Physical weight received at warehouse gate
  - `variance_qty` (decimal(10,2), default 0) - Calculated discrepancy `(received_qty - procured_qty)`
  - `status` (varchar(30), index) - `pending`, `ordered`, `received`, `discrepancy`
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 7. `warehouse_receipts`
* **Purpose**: Records incoming supplier deliveries at the warehouse gate.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `procurement_batch_id` (bigint, unsigned, FK $\rightarrow$ `procurement_batches.id`)
  - `receipt_number` (varchar(50), unique, index) - Unique gate-entry GRN number (e.g., `REC-20260525-001`)
  - `received_at` (timestamp)
  - `received_by` (bigint, unsigned, FK $\rightarrow$ `users.id`)
  - `state` (varchar(30), index) - Status machine status
  - `discrepancy_flag` (boolean, default false)
  - `notes` (text, nullable)
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 8. `sorting_allocations`
* **Purpose**: Records the sorting, quality grading, and physical segregation of incoming vegetables to target shop orders.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `warehouse_receipt_id` (bigint, unsigned, FK $\rightarrow$ `warehouse_receipts.id`)
  - `shop_order_item_id` (bigint, unsigned, FK $\rightarrow$ `shop_order_items.id`)
  - `product_id` (bigint, unsigned, FK $\rightarrow$ `products.id`)
  - `allocated_qty` (decimal(10,2)) - Quantity segregated for this shop order item
  - `picked_qty` (decimal(10,2), default 0) - Quantity packed into crates
  - `status` (varchar(30), index) - `pending`, `allocated`, `picked`, `packed`
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 9. `dispatches`
* **Purpose**: Outbound dispatch route logs.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `dispatch_number` (varchar(50), unique, index) - e.g., `DSP-20260525-001`
  - `route_id` (bigint, unsigned, nullable)
  - `vehicle_id` (bigint, unsigned, nullable)
  - `driver_id` (bigint, unsigned, nullable, FK $\rightarrow$ `users.id`)
  - `state` (varchar(30), index) - e.g., `planned`, `loaded`, `dispatched`, `delivered`
  - `loaded_at` (timestamp, nullable)
  - `dispatched_at` (timestamp, nullable)
  - `created_by` (bigint, unsigned, FK $\rightarrow$ `users.id`)
  - `created_at` (timestamp)
  - `updated_at` (timestamp)

### 10. `delivery_logs`
* **Purpose**: Shop-level delivery confirmations and returns logging.
* **Fields**:
  - `id` (bigint, PK, unsigned, auto_increment)
  - `dispatch_id` (bigint, unsigned, FK $\rightarrow$ `dispatches.id` ON DELETE CASCADE)
  - `shop_id` (bigint, unsigned, FK $\rightarrow$ `shops.id`)
  - `delivered_at` (timestamp, nullable)
  - `delivered_by` (bigint, unsigned, nullable, FK $\rightarrow$ `users.id`)
  - `status` (varchar(30)) - `pending`, `delivered`, `rejected_short`
  - `remarks` (text, nullable)
  - `proof_reference` (varchar(255), nullable) - Path to proof-of-delivery signature/image
  - `created_at` (timestamp)
  - `updated_at` (timestamp)
