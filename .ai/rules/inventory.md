---
paths:
  - 'app/Actions/Inventory/**'
---

# Inventory

## Post every stock change through the ledger
Use PostInventory as the only stock mutation path. Lock the product row and write the immutable ledger entry plus cached stock balance in one transaction. Require stable idempotency data and use exact fixed-scale quantity strings, never floats.
