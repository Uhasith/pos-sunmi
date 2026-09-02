# Changelog

Significant project changes are recorded here for future development work.

## Unreleased

### Added

- Signed, delivery-deduplicated WooCommerce order webhook intake that retains safe identifiers and a payload hash without storing customer payload bodies.
- Authenticated newest-first POS order listing and receipt-ready detail retrieval by immutable order number.
- Atomic, idempotent `POST /api/v1/checkouts` cash sales with catalog/manual lines, server-side VAT totals, immutable receipt snapshots, cash tender/change, and paid order/payment records.
- Whole-unit POS sale ledger postings linked to order items, with deterministic product locking and after-commit WooCommerce stock projection for mapped products.
- Guarded MySQL concurrency coverage proving that two devices cannot both sell the final unit.
- Explicit WooCommerce product/variation mappings managed through Filament.
- Retry-safe after-commit stock projection jobs that send Laravel's current integer balance to mapped WooCommerce products and variations.
- Visible permanent failure for fractional stock without making a WooCommerce request.

## 2026-08-28

### Added

- Sanctum bearer-token authentication for pre-provisioned active POS devices and a versioned `/api/v1` boundary.
- Filament device provisioning and activation management without destructive device actions.
- Authenticated active-product listing, detail, search, and exact barcode lookup resources for the Android client.
- Authenticated inventory-ledger reads and idempotent manager/admin stock adjustments through `PostInventory`.
- Safe API error envelopes with request correlation IDs.
- Read-only WooCommerce REST API credential check using HTTPS basic authentication.
- Inventory reconciliation command that reports cached-stock mismatches without modifying data.
- Read-only Filament inventory ledger under `/admin/inventory-ledger` with product, signed quantity delta, transaction type, stock before/after, reason, actor, and timestamp.
- Filament 5 product management under `/admin/products` with VAT, pricing, supplier, brand, and catalog validation.
- Manager/admin stock adjustments that post signed decimal quantities through the idempotent inventory ledger with a mandatory reason.
- Idempotent local demo-product seeding with opening stock posted through the ledger.
- Focused tests for product validation, protected stock balances, stock-adjustment retries, and repeatable demo seeding.

### Preserved

- Laravel remains authoritative for catalog and stock business rules.
- Direct `stock_on_hand` edits remain disabled; every stock change uses `PostInventory`.
