---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Idempotent after-commit integrations
Never call WooCommerce or payment providers inside local business transactions. Dispatch external work after commit; make jobs retry-safe with bounded retries, safe logging, and stable source identities. Stock sync sends Laravel's current balance, not a delta.

## Expose Woo stock projection outcomes safely
Successful Woo stock projections record the product's last success and clear prior failure state. Terminal failures record only a safe failure code and timestamp; managers/admins retry through the same product-unique job, and fractional balances remain blocked until corrected.
