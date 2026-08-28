---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Idempotent after-commit integrations
Never call WooCommerce or payment providers inside local business transactions. Dispatch external work after commit; make jobs retry-safe with bounded retries, safe logging, and stable source identities. Stock sync sends Laravel's current balance, not a delta.
