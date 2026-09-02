---
paths:
  - 'app/Http/**'
---

# Http

## Versioned POS API boundary
Expose the Android contract under a versioned JSON API. Validate and authorize at the request boundary, return Eloquent Resources and the shared safe error envelope, and delegate transactions and business rules to domain actions. Checkout and return writes require idempotency keys.

## Detailed sale responses carry receipt identity
Checkout, order detail, and receipt responses include environment-backed structured shop identity for Android receipt rendering. Order list rows omit it; real legal values remain a deployment requirement.
