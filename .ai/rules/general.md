---
paths:
  - '**'
---

# General

## POS money, stock, and database defaults
Selling prices are VAT-inclusive; purchase prices are VAT-exclusive. Store quantities as decimal(15,3). POS sales must not reduce stock below zero. Only managers and admins may post manual stock adjustments. Production uses MySQL. Revisit these choices through an explicit migration and behavior decision if requirements change.
