---
paths:
  - 'tests/**'
---

# Tests

## Protect POS invariants with focused tests
Add the smallest behavioral test for every changed branch or transaction. Money, VAT, inventory, payment, return, webhook, and synchronization paths must prove failure rollback and idempotent retry behavior as applicable.
