---
paths:
  - 'database/migrations/**'
---

# Migrations

## POS storage invariants
Target production MySQL. Store money as integer cents and stock quantities as decimal(15,3). Preserve inventory and financial history with constraints; never introduce a stock balance write that can exist without its ledger entry.
