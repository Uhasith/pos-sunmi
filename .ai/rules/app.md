---
paths:
  - 'app/**'
---

# App

## Laravel owns POS business truth
Keep products, accepted prices, VAT, orders, payments, inventory, returns, reporting, and synchronization semantics in Laravel domain code. Controllers, Filament resources, jobs, and clients are adapters; they must call domain actions instead of duplicating rules.

## Woo stock projection authority and mapping
Laravel is the authoritative stock owner. Outbound WooCommerce stock sync must use explicit local product/variation IDs, never runtime SKU guessing. Woo accepts integer stock; if Laravel stock has a fractional component, fail visibly and do not round.
