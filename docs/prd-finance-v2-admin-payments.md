# Green Leaf ERP — Finance V2 and Admin Payments PRD

**Status:** Implementation complete (core Finance V2 Payments program)  
**Primary objective:** Consolidate company payments, shop payments, company payables, petty balances and shop receivables into one reliable Finance V2 workflow.

## Summary

Create a consolidated Finance V2 system where Green Leaf administrators can view the complete financial position, receivables/payables per shop, review company-payable expenses, settle against shop payments or pay directly, record bill payments, view Petty balances, and receive admin notifications — while reusing existing payment, accounting and journal services.

## Key requirements

1. Multi-client Finance V2 (remove hardcoded `AISHWARYA_VEG` dependency).
2. Dedicated `finance.*` permissions and `lockForUpdate()` on financial approvals.
3. UI rename Loan → Petty (preserve DB/class names).
4. Expense `funding_source`: `sales` | `petty` | `company`.
5. Company payable workflow with settlements table and journals (account 2200).
6. Admin Payments: Direct Payments + Client Payments (reuse ShopInvoiceService / PurchaseInvoicePayment).
7. Dashboard cards, net client position, alerts, ageing reports.
8. Laravel database notifications + dashboard counts.

## Implementation order

See approved development order in the implementation plan. Reuse `ShopInvoiceService`, `PurchaseInvoicePayment`, `JournalService`, `ShopLoanService`, `OwnedShopAccountingService`. Do not create duplicate payment engines. Do not auto-journal every shop cashbook line.

## Definition of done

- Finance V2 supports all clients and shops.
- Shop owners select Sales / Petty / Company for expenses.
- Company expenses create pending company payables with admin notification.
- Admin can approve/reject, adjust against shop payment, or pay directly (including partial).
- Petty balances remain correct; existing Shop Payments reused; Direct bill payments work.
- Journals post correctly; duplicate settlement prevented; closed-period rules remain.
- Historical records remain intact.

## Reference

Full product requirements, data model, journal rules, validation, testing, and phases are defined in the project Finance V2 / Admin Payments planning document. This file is the repo PRD entrypoint for implementation tracking.
