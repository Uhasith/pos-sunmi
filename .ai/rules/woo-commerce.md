---
paths:
  - 'app/Actions/WooCommerce/**'
---

# Woo Commerce

## Mirror Woo stock-affecting transitions
For inbound Woo orders, post stock once when an order first reaches on-hold, processing, or completed. Restore only when that local Woo sale effect exists and Woo explicitly reports failed/cancelled restoration or a refund marked as restocked; never infer refunds from status alone.
