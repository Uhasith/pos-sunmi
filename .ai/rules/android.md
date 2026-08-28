---
paths:
  - 'android/**'
---

# Android

## Kotlin client responsibility boundary
Keep the Android app responsible for Sunmi UI, scanner input, local cart/retry state, server-status display, and ESC/POS network printing. Never duplicate Laravel pricing, VAT, payment-success, inventory, return, or synchronization rules in Kotlin.
