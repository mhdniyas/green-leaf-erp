# Redesign Notes & Legacy Code Audit

This document lists the specific files, structures, and migrations that were built under the previous, incorrect **sales-centric (Purchase → Sorting → Sales)** assumption. 

Per the workflow pivot, these assets **must not be deleted**. They must remain in the codebase to preserve the current Laravel foundation, but they are marked as **Legacy/Obsolete** and will be deprecated, refactored, or repurposed in subsequent sprints.

---

## 🔍 Audited Legacy Files & Components

### 1. Eloquent Models (`app/Models/`)
These models reflect external B2B customers and traditional sales workflows, whereas the real business operates on internal Shop demand.
- **[Customer.php](file:///Users/niyas/Sites/green-leaf-erp/app/Models/Customer.php)**: Models external customers. Will be replaced by `Shop`.
- **[CustomerGradePrice.php](file:///Users/niyas/Sites/green-leaf-erp/app/Models/CustomerGradePrice.php)**: Models external customer-specific grade pricing. Pricing/grading logic will be redirected to standard product cost allocation and shop configurations.
- **[SalesOrder.php](file:///Users/niyas/Sites/green-leaf-erp/app/Models/SalesOrder.php)**: Models customer order placement. Will be replaced by `ShopOrder`.
- **[SalesInvoice.php](file:///Users/niyas/Sites/green-leaf-erp/app/Models/SalesInvoice.php)**: Models external sales invoicing. Finance components will be aligned directly to dispatch/delivery completion rather than external order generation.

### 2. Business Logic Layer (`app/Services/` and `app/Repositories/`)
- **[CustomerService.php](file:///Users/niyas/Sites/green-leaf-erp/app/Services/Sales/CustomerService.php)** / **[CustomerRepository.php](file:///Users/niyas/Sites/green-leaf-erp/app/Repositories/Sales/CustomerRepository.php)**: Logic for managing B2B customers.
- **[SalesOrderService.php](file:///Users/niyas/Sites/green-leaf-erp/app/Services/Sales/SalesOrderService.php)**: Logic for traditional sales order lifecycle management.
- **[SalesInvoiceService.php](file:///Users/niyas/Sites/green-leaf-erp/app/Services/Sales/SalesInvoiceService.php)**: Logic for sales billing.

### 3. Controllers & Requests (`app/Http/`)
- **[CustomerController.php](file:///Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/Sales/CustomerController.php)** / **[StoreCustomerRequest.php](file:///Users/niyas/Sites/green-leaf-erp/app/Http/Requests/Web/Sales/StoreCustomerRequest.php)** / **[UpdateCustomerRequest.php](file:///Users/niyas/Sites/green-leaf-erp/app/Http/Requests/Web/Sales/UpdateCustomerRequest.php)**: Form validation and routing for customer CRUD.
- **[SalesOrderController.php](file:///Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/Sales/SalesOrderController.php)** / **[StoreSalesOrderRequest.php](file:///Users/niyas/Sites/green-leaf-erp/app/Http/Requests/Web/Sales/StoreSalesOrderRequest.php)**: Web endpoints for sales orders.
- **[SalesInvoiceController.php](file:///Users/niyas/Sites/green-leaf-erp/app/Http/Controllers/Web/Sales/SalesInvoiceController.php)**: Invoicing endpoints.

### 4. Data Transfer Objects (`app/DTOs/`)
- **[CustomerData.php](file:///Users/niyas/Sites/green-leaf-erp/app/DTOs/Sales/CustomerData.php)**: DTO representing customer request fields.
- **[SalesOrderData.php](file:///Users/niyas/Sites/green-leaf-erp/app/DTOs/Sales/SalesOrderData.php)**: DTO representing sales order request fields.

### 5. Authorization Policies (`app/Policies/`)
- **[CustomerPolicy.php](file:///Users/niyas/Sites/green-leaf-erp/app/Policies/CustomerPolicy.php)**: Policies governing access to customer resource operations.

### 6. Database Migrations (`database/migrations/`)
The following migration schemas contain tables representing the old sales flow:
- `2026_05_23_031530_create_customers_table.php` (replaces with `shops`)
- `2026_05_23_031531_create_customer_grade_prices_table.php` (obsolete)
- `2026_05_23_031532_create_sales_orders_table.php` (replaces with `shop_orders`)
- `2026_05_23_031534_create_sales_order_items_table.php` (replaces with `shop_order_items`)
- `2026_05_23_031536_create_sales_invoices_table.php` (obsolete/re-align)
- `2026_05_23_031538_create_payments_table.php` (re-align with delivery execution/reconciliation)

---

## 🔄 Redesign Roadmap & Conceptual Mapping

To pivot without breaking the current base, we establish a clean 1-to-1 migration mapping for the next sprint:

| Legacy Concept | Pivot Mapping / New Design | Rationale for Redesign |
|---|---|---|
| **Customers** | **Shops** | The demand centers are internal, owned, or affiliated retail shops rather than arbitrary external retail/wholesale customers. |
| **Sales Orders** | **Shop Orders** | Daily shop demand submission replaces traditional sales orders. A shop order captures what a shop owner needs, not what was sold. |
| **Sales Order Items**| **Shop Order Items** | Itemized product demands requested by shops. Includes requested and approved quantities. |
| **Customer Grade Prices**| **Standard Product Allocation** | Pricing is not customizable per-customer grade; instead, standard landed cost allocation applies to sorting yields. |
| **Sales Invoices** | **Fulfillment Billing** | Invoicing is obsolete; replaced by dispatch route logs and delivery confirmations mapped to physical shop fulfillment records. |
| **Payments** | **Daily Cash/Route Recon** | Payments are reconciled per route dispatch and driver collection sheets, rather than invoice-by-invoice collections. |
