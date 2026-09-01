---
paths:
  - 'app/Actions/Checkout/**'
---

# Checkout

## Cash checkout invariants
Cash POS checkout is device-scoped and idempotent: an equivalent retry returns stored snapshots before live catalog validation, while changed input conflicts. Checkout accepts whole-unit quantities, uses EUR and Europe/Amsterdam receipt time, and extracts 9%/21% VAT from inclusive line totals with integer half-up rounding. Catalog lines post negative `pos_sale` inventory through `PostInventory`; manual lines never affect products or stock.
