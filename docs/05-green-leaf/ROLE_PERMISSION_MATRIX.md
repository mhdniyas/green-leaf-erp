# Role Permission Matrix

This document maps operational capabilities to their respective **Spatie Permission Keys** across all ERP roles.

---

## 📊 Permission Matrix

| Capability | Permission Key | Shop Owner | Purchase Manager | Warehouse Manager | Picker | Driver | Admin | Viewer |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **View product catalog** | `products.view` | Yes | Yes | Yes | Yes | Limited | Yes | Yes |
| **Create shop order draft** | `shop-orders.create` | Yes | No | No | No | No | Yes | No |
| **Submit shop order** | `shop-orders.submit` | Yes | No | No | No | No | Yes | No |
| **View own shop orders** | `shop-orders.view-own` | Yes | Yes | Limited | No | No | Yes | Yes |
| **View all shop orders** | `shop-orders.view-all` | No | Yes | Limited | No | No | Yes | Yes |
| **Approve / reject shop order** | `shop-orders.approve` | No | Yes | No | No | No | Yes | No |
| **Approve late submission override** | `shop-orders.override` | No | Yes | No | No | No | Yes | No |
| **View consolidated procurement** | `procurement.view` | No | Yes | Limited | No | No | Yes | Yes |
| **Manage supplier procurement** | `procurement.manage` | No | Yes | No | No | No | Yes | No |
| **Record warehouse receipt** | `warehouse.receive` | No | No | Yes | No | No | Yes | No |
| **Validate discrepancies** | `warehouse.validate-discrepancy` | No | Yes | Yes | No | No | Yes | No |
| **Manage sorting allocations** | `sorting.manage` | No | No | Yes | No | No | Yes | No |
| **Generate / view picking list** | `dispatch.picking.view` | No | No | Yes | Yes | No | Yes | Limited |
| **Confirm picking completion** | `dispatch.picking.complete` | No | No | Yes | Yes | No | Yes | No |
| **Manage route loading** | `dispatch.loading.manage` | No | No | Yes | No | No | Yes | No |
| **View assigned dispatch** | `dispatch.view-assigned` | No | No | Limited | No | Yes | Yes | Limited |
| **Confirm dispatch handover** | `dispatch.handover` | No | No | Yes | No | No | Yes | No |
| **Confirm delivery outcome** | `dispatch.delivery.complete` | No | No | No | No | Yes | Yes | No |
| **View reports** | `reports.view` | Limited | Yes | Yes | Limited | Limited | Yes | Yes |
| **View audit logs** | `audit.view` | No | Limited | Limited | No | No | Yes | Yes |
| **Manage users, roles, settings** | `system.manage` | No | No | No | No | No | Yes | No |

---

## 📝 Matrix Notes & Definitions

### 1. Visibility Scopes
* **Yes**: Full access to all resources in this domain.
* **Limited**: Role-scoped visibility only:
  - **Shop Owner**: Can only view orders/reports for their assigned shop.
  - **Driver**: Can only view dispatches assigned to their vehicle/user ID.
  - **Warehouse Picker**: Can only view picking lists assigned to them.
* **No**: No access allowed (middleware blocks access).

### 2. Spatie RBAC Architecture
- Roles (e.g., `Shop Owner`, `Purchase Manager`) are assigned to users.
- Permissions (e.g., `shop-orders.approve`) are assigned directly to roles.
- Blade layouts and API middleware must check permissions (`$user->can('shop-orders.approve')`) rather than role names to ensure clean decoupling and customizable role adjustments.
