# Old Database Assumptions Archive

This archive preserves the previous database direction without deleting any existing migrations, factories, repositories, services, or references.

## Why this is archived

The earlier design mixes procurement and warehouse concepts with a traditional customer-sales ERP flow.
The real Green Leaf ERP flow is centralized multi-shop demand collection, approval, procurement, warehouse allocation, dispatch, delivery, reporting, and audit.

## Assumptions identified for redesign

### Traditional sales assumptions
- customers
- customer_grade_prices
- sales_orders
- sales_order_items
- sales_invoices
- payments tied to customer sales workflow
- Sales DTO / Service / Repository layering

### Purchase-first assumptions that need re-sequencing
- purchase_orders as the primary business trigger
- goods_receiveds centered around direct purchasing instead of approved consolidated shop demand
- inventory movement modeled before shop demand approval lifecycle

## Preserve, do not delete

The following should remain in place until mapped and formally deprecated or repurposed:
- existing migrations
- factories
- seeders
- services
- repositories
- API resources
- enums and DTOs

## Redesign direction

Future schema should pivot toward:
- shops
- shop_orders
- shop_order_items
- approvals
- procurement_batches
- procurement_items
- warehouse_receipts
- sorting_allocations
- dispatches
- delivery_logs
- finance/reporting entities aligned to fulfillment lifecycle
- audit traceability across all state changes
