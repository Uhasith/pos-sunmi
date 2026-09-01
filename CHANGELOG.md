# Changelog

Significant project changes are recorded here for future development work.

## Unreleased

## 2026-08-28

### Added

- Sanctum bearer-token authentication for pre-provisioned active POS devices and a versioned `/api/v1` boundary.
- Filament device provisioning and activation management without destructive device actions.
- Authenticated active-product listing, detail, search, and exact barcode lookup resources for the Android client.
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
