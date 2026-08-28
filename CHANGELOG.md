# Changelog

Significant project changes are recorded here for future development work.

## Unreleased

## 2026-08-28

### Added

- Filament 5 product management under `/admin/products` with VAT, pricing, supplier, brand, and catalog validation.
- Manager/admin stock adjustments that post signed decimal quantities through the idempotent inventory ledger with a mandatory reason.
- Idempotent local demo-product seeding with opening stock posted through the ledger.
- Focused tests for product validation, protected stock balances, stock-adjustment retries, and repeatable demo seeding.

### Preserved

- Laravel remains authoritative for catalog and stock business rules.
- Direct `stock_on_hand` edits remain disabled; every stock change uses `PostInventory`.
