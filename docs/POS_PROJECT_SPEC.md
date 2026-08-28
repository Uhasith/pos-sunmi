# Laravel + Kotlin Sunmi POS — Project Specification

> Status: implementation specification
> Intended location: repository root as `POS_PROJECT_SPEC.md`
> Audience: developers and AI coding agents
> Priority: the Sunmi Android POS application, supported by a reliable Laravel backend

## 1. Instructions for the coding agent

Read this file completely before changing code. Treat it as the product and architecture contract for the first production version.

When implementing:

1. Inspect the current repository, installed framework versions, conventions, and tests before writing code.
2. Implement only the current roadmap phase and its prerequisites. Do not scaffold later phases “for completeness.”
3. Reuse existing project conventions and installed dependencies. Prefer Laravel, Kotlin, Android, SQL, and platform features over new packages.
4. Keep Laravel authoritative for products, prices used at checkout, VAT calculations, order/payment state, inventory, returns, reporting, and WooCommerce synchronization.
5. Keep the Kotlin app responsible for the cashier UI, Sunmi scanner integration, local device state, and local-network receipt printing. Do not duplicate backend business rules in Kotlin.
6. Store money as integer cents and quantities as signed decimal values with an explicitly chosen database precision. Never use binary floating point for money.
7. Put every stock change through the inventory ledger. Never update a stock balance without a corresponding ledger entry in the same database transaction.
8. Make checkout, payment callbacks, returns, WooCommerce imports, and stock synchronization idempotent.
9. Add the smallest relevant automated test for each non-trivial rule. Payment, inventory, VAT, and idempotency paths require tests.
10. If this specification conflicts with observed customer-reference behavior, document the conflict and request a product decision. Do not silently copy the reference behavior.
11. Do not change an architectural rule in this file without explaining the need, impact, migration path, and tests.
12. After each phase, update a short implementation checklist and record any deliberate deviation from this specification.

Use the stable framework versions already selected by the fresh projects. Before adding an integration, consult the current official documentation for Laravel, Android/Sunmi, WooCommerce, MultiSafepay, and the receipt-printer model. Do not invent SDK methods or pin versions based solely on this document.

## 2. Product objective

Build a production-ready point-of-sale and inventory system consisting of:

- a Laravel API and administration application;
- a native Kotlin Android application for a Sunmi device;
- stock and order synchronization with WooCommerce;
- card payments through MultiSafepay and cash payments without MultiSafepay;
- receipt and negative-receipt printing to a remote Wi-Fi ESC/POS printer;
- unified store and WooCommerce orders, returns, VAT reporting, and purchase-order advice.

The first milestone must prove the complete operational path:

> A cashier scans an existing product on the Sunmi device, completes a cash sale, prints a receipt, and sees the resulting stock reflected in Laravel and subsequently synchronized to WooCommerce.

## 3. Scope and requirements

### 3.1 Customer requirements 1–11

1. **WooCommerce stock synchronization.** Stock changes from store sales, returns, adjustments, and imported WooCommerce activity are recorded in Laravel and the resulting balance is pushed to WooCommerce.
2. **Inventory management.** Products support title, selling price, current stock, article number, purchase price, brand, WooCommerce article/product identifier, minimum stock, supplier, and VAT rate of 9% or 21%. A barcode and active flag are also required to operate the POS safely.
3. **Purchase-order advice.** Products below their minimum stock appear in advice grouped by supplier.
4. **Sunmi Android app.** The cashier application is native Android written in Kotlin and supports the target Sunmi hardware.
5. **MultiSafepay.** Card payment attempts are created and verified through MultiSafepay.
6. **Remote receipt printer.** The Sunmi device prints receipts over the local Wi-Fi network to a compatible ESC/POS printer.
7. **Unified order list and reporting.** Store and WooCommerce orders appear together. Reports/export provide amount excluding VAT, 9% VAT, and 21% VAT for a month, quarter, year, or custom period.
8. **Negative receipts/returns.** Store and WooCommerce returns create auditable negative financial lines and negative receipts. Stock is restored only where the return disposition says it is resellable/restocked.
9. **Cash and card.** Cash checkout never contacts MultiSafepay. Card checkout does. The server, not the app, confirms a successful card payment.
10. **Barcode sales.** The Sunmi scanner can find a product by barcode and add/increment it in the cart.
11. **Manual products.** Because the original sentence was incomplete, V1 defines this as a manual/open sale line: description, quantity, price including VAT, and VAT rate. It does not create a permanent inventory product and does not affect stock. If the customer instead means “create a new catalog product from the POS,” that is a separate approved requirement.

### 3.2 V1 exclusions

Do not add these without a new requirement:

- multi-store or multi-warehouse allocation;
- loyalty, discounts, gift cards, customer accounts, or staff commissions;
- automatic purchase-order submission;
- demand forecasting, lead times, order multiples, or desired-stock algorithms;
- arbitrary VAT rates in the cashier UI;
- offline card authorization;
- a custom printer framework beyond the one required ESC/POS network implementation;
- bidirectional “last write wins” stock editing;
- microservices, event sourcing, or separate databases per client.

The schema may use a `tax_rates` table to preserve maintainability, but the enabled V1 rates are 9% and 21% only.

## 4. System architecture

```text
┌──────────────────────────────┐
│ Sunmi Android POS (Kotlin)   │
│ scanner • cart • checkout    │
│ orders • returns • printing  │
└──────────────┬───────────────┘
               │ HTTPS JSON API
┌──────────────▼───────────────┐
│ Laravel monolith             │
│ API • admin • domain rules   │
│ jobs • webhooks • reports    │
└───────┬───────────┬──────────┘
        │           │
        │           └────────► MultiSafepay
        └────────────────────► WooCommerce

┌──────────────────────────────┐
│ Relational database          │
│ canonical business records   │
└──────────────────────────────┘

Sunmi ──local Wi-Fi/TCP───────► ESC/POS printer
```

Use a Laravel monolith with a relational database and a queue worker. This keeps transactions around orders, payments, and stock in one consistency boundary. Separate services are not justified for V1.

### 4.1 Responsibility boundary

Laravel owns:

- canonical product and stock records;
- price/VAT validation and monetary calculation;
- order numbers and order state;
- inventory ledger posting;
- payment attempts and verified payment state;
- WooCommerce import/export and reconciliation;
- returns, refund records, reporting, exports, PO advice;
- users, devices, authorization, audit history.

The Kotlin app owns:

- login/device session UI;
- scanner input, product search, cart presentation;
- a short-lived local checkout request and retry state;
- payment-status display/polling;
- receipt rendering from server-provided receipt data and ESC/POS delivery;
- printer settings/test print and visible recovery actions.

The app may estimate cart totals for immediate display, but Laravel recalculates and returns the accepted totals at checkout. A mismatch must be shown to the cashier before payment is finalized where practical.

## 5. Source-of-truth and synchronization rules

### 5.1 Canonical ownership

- **Laravel is the stock authority.** `products.stock_on_hand` is the cached current balance backed by immutable inventory ledger entries.
- **WooCommerce is authoritative for the lifecycle and financial facts of WooCommerce-originated orders/refunds.** Laravel imports those external facts exactly once, translates eligible events into local order/return and inventory effects, and then remains the reporting copy.
- **Laravel pushes stock balances to WooCommerce.** WooCommerce product stock is a projection, not a competing master.
- Product catalog ownership in V1 is Laravel for fields managed by this POS. An initial WooCommerce import is allowed. After cutover, externally edited catalog fields must follow an explicit field-ownership map; no generic bidirectional overwrite is allowed.
- Order-item financial snapshots are immutable historical truth even if a product price or VAT rate changes later.

### 5.2 Stock flows

Store sale:

```text
Sunmi checkout → Laravel order transaction → sale ledger entries
→ cached stock balances → queued WooCommerce stock push
```

WooCommerce sale:

```text
verified webhook/reconciliation → idempotent order import
→ Woo sale ledger entries → cached balances → stock push to WooCommerce
```

Return:

```text
approved return → negative financial records
→ return ledger entry only for restockable quantity → Woo stock push
```

External integrations are never called inside the database transaction that creates an order or ledger entry. Commit locally, then dispatch an idempotent queued job.

### 5.3 Overselling rule

V1 does not permit a POS catalog line to reduce stock below zero unless an authorized administrator enables/executes an explicit adjustment. Checkout locks each affected product row in a stable ID order, verifies available quantity, and posts all sale ledger entries atomically. Manual/open lines are exempt because they have no inventory product.

WooCommerce may still deliver an externally accepted order that exceeds Laravel stock. Import it rather than losing the order, allow the balance to become negative, raise an operational alert, and push the resulting balance according to WooCommerce’s valid stock rules (normally zero available). This exception must be visible in audit/stock reports.

## 6. Laravel domain model

Names are recommendations; follow existing project naming conventions where equivalent. All mutable records need timestamps. Use soft deletion only where recovery/business history requires it; never soft-delete ledger or financial history.

### 6.1 Core tables

#### `users`

- `id`, name, email/login, password hash, active
- roles/permissions: at minimum `cashier`, `manager`, `admin`

#### `devices`

- `id`, UUID, name, active, last_seen_at
- assigned/authenticated user relationship as appropriate
- printer host, port, paper width, and character encoding, or a separate one-to-one `device_printer_settings` table
- never store general API secrets in plaintext

#### `brands`

- `id`, unique name, active

#### `suppliers`

- `id`, name, optional contact/reference fields, active

#### `tax_rates`

- `id`, code, label, rate basis points/decimal, active, effective dates if later required
- seed `VAT_9 = 9.00%` and `VAT_21 = 21.00%`

#### `products`

- `id`
- `title`
- unique `article_number` (SKU)
- unique nullable `barcode`
- `selling_price_including_tax_cents`
- `purchase_price_excluding_tax_cents` (confirm accounting interpretation during kickoff)
- signed decimal `stock_on_hand`
- non-negative decimal `minimum_stock`
- `brand_id`, `supplier_id`, `tax_rate_id`
- nullable unique `woocommerce_product_id`
- nullable `woocommerce_variation_id`; uniqueness applies to the product/variation mapping
- `active`
- `stock_synced_at`, `woocommerce_synced_at` or equivalent operational metadata

Validate title, unique identifiers, non-negative prices/minimum, permitted VAT, and identifier length at the request boundary. Barcode is stored as text so leading zeroes survive.

#### `inventory_transactions`

- `id`, sortable UUID/ULID if preferred by project convention
- `product_id`
- signed `quantity_delta`
- `type`: `opening_balance`, `pos_sale`, `woocommerce_sale`, `store_return`, `woocommerce_return`, `manual_adjustment`, `purchase_receipt`, `stock_count`, `correction`
- polymorphic or explicit reference to order item, return item, adjustment, etc.
- `stock_before`, `stock_after`
- nullable reason/note
- `actor_user_id`, nullable for integrations
- `idempotency_key` or unique source event key where applicable
- `created_at`; no update/delete application path

Use a unique constraint that prevents a source item/event from posting the same stock effect twice.

#### `orders`

- `id`, immutable unique human-readable `order_number`
- `source`: `pos` or `woocommerce`
- nullable external ID with unique `(source, external_id)`
- nullable `device_id`, `cashier_user_id`
- `currency` (V1 expected EUR)
- integer cents: `subtotal_excluding_tax`, `tax_9`, `tax_21`, `tax_total`, `total_including_tax`
- `status`: `draft`, `pending_payment`, `paid`, `cancelled`, `partially_returned`, `returned`
- `payment_status`: `unpaid`, `pending`, `paid`, `partially_refunded`, `refunded`, `failed`
- `payment_method`: `cash`, `card`, `online`, nullable before checkout
- `ordered_at`, `paid_at`, nullable external timestamps
- POS checkout idempotency key, unique per device/client scope

Do not treat imported WooCommerce cancellation as a new return unless the external order had already posted stock/financial effects and the external event explicitly restores/refunds them.

#### `order_items`

- `id`, `order_id`, nullable `product_id`
- immutable SKU/article-number and description snapshots
- quantity
- integer cents: unit price including tax, unit price excluding tax, line excluding tax, tax amount, line including tax
- tax-rate snapshot (9.00 or 21.00 in V1)
- nullable external line ID; unique within an external order
- `is_manual`

For manual items: `product_id = null`, `is_manual = true`, description/quantity/price/VAT are required, and no inventory entry is created.

#### `payments`

- `id`, `order_id`, `method`: `cash`, `multisafepay_card`, `woocommerce_online`
- amount/currency
- status: `created`, `pending`, `paid`, `failed`, `cancelled`, `expired`, `partially_refunded`, `refunded`
- provider and unique provider payment/order identifier
- client idempotency key/provider request key
- provider status and safe response metadata (no card data)
- initiated/paid/failed timestamps

Allow multiple attempts for one order, but only one successful capture for the payable amount unless a later split-payment requirement is approved.

#### `returns` and `return_items`

`returns`:

- `id`, unique return/negative-receipt number
- `source`: `pos` or `woocommerce`
- original `order_id` where known
- external refund ID with source-scoped uniqueness
- status: `pending`, `completed`, `cancelled`
- refund method/status
- negative monetary totals split by VAT
- reason, actor, device, returned/completed timestamps
- client idempotency key for store returns

`return_items`:

- `return_id`, original `order_item_id`
- returned quantity and immutable description/SKU snapshots
- negative excluding-tax, VAT, and including-tax amounts
- tax-rate snapshot
- `restock_quantity` and disposition/reason

Enforce cumulative returned quantity per original item not exceeding sold quantity. The amount derives proportionally from the original immutable line data; do not use current catalog pricing.

#### Integration and audit tables

- `webhook_receipts`: provider, unique event/delivery ID or payload hash fallback, received/processed status, attempt/error metadata, safe payload reference
- `woocommerce_sync_states`: resource/cursor/high-water mark and last successful reconciliation time
- `woocommerce_sync_logs`: direction, resource IDs, status, attempts, error summary
- `audit_logs`: actor, action, subject, before/after changed fields where appropriate, device/IP/request correlation ID, timestamp

Avoid a duplicate `woocommerce_orders` business table. External metadata belongs on the unified order or in integration logs.

### 6.2 Optional later tables

Do not create `purchase_orders` merely for the advice screen. V1 PO advice is a calculated read model. Add purchase-order and receiving tables only when the customer asks to create/place/receive actual POs.

## 7. Inventory ledger design

### 7.1 Invariant

For each product:

```text
new stock_on_hand = previous stock_on_hand + quantity_delta
```

The ledger insert and product cached-balance update occur in one database transaction under a row lock. A reusable domain service/action must be the only application path that changes stock. It accepts product, delta, type, reference, actor, and idempotency/source key.

### 7.2 Posting rules

- POS paid sale: negative quantity exactly once.
- Decide stock timing for card orders as follows: V1 posts stock only when payment becomes `paid`; prevent simultaneous POS overselling by revalidating under lock at that transition. If payment terminals require a reservation window later, add explicit reservations with expiry rather than silently deducting stock early.
- Cash sale: payment and order become paid and stock posts in one local transaction.
- Imported eligible WooCommerce order: negative quantity once per external line/event.
- Restockable completed return: positive quantity once.
- Non-restockable return: no positive inventory entry.
- Manual/open line: no inventory entry.
- Adjustment/count: signed difference, mandatory reason and authorized actor.
- Product opening stock: create an `opening_balance` entry; do not seed only the cached field.

### 7.3 Reconciliation

Provide an admin/console check that recomputes `SUM(quantity_delta)` per product and compares it with `stock_on_hand`. It reports differences and exits unsuccessfully; it must not auto-repair without an explicit administrator action. Periodically run it and alert on mismatches.

## 8. Money and VAT calculation

Assume catalog and manual prices entered at POS include VAT, consistent with consumer retail. Confirm this with the customer before Phase 2; changing it after production affects UI and reporting.

For a VAT-inclusive line total in integer cents:

```text
line_total_including_tax = unit_price_including_tax × quantity
line_excluding_tax = round(line_total_including_tax × 100 / (100 + tax_rate))
line_tax = line_total_including_tax - line_excluding_tax
```

Define and test one rounding policy, using the currency’s minor unit and calculating at line level. Order totals are the sum of stored line totals, never independently recalculated from current products. Allocate proportional partial-return cents deterministically so that returning the remaining quantity closes exactly to the original line total.

Reports include completed/paid orders and completed returns in the selected date interval. Returns contribute negative amounts. Cancelled, failed, draft, and unpaid orders are excluded. The report timezone is the configured shop timezone; persist database timestamps in UTC.

## 9. WooCommerce integration

### 9.1 Configuration

Keep WooCommerce base URL and credentials in server-side secret configuration, never in the Android app or repository. Record connection status without exposing secrets.

### 9.2 Initial import and mapping

1. Import/map products using immutable WooCommerce product/variation IDs; do not rely on titles.
2. Require a unique article number and/or barcode locally. Report collisions for manual resolution.
3. Import the required historical/open order window with an explicit cutoff.
4. Establish Laravel stock balances and ledger opening entries.
5. Run a dry reconciliation report before enabling outbound stock updates.
6. Record a cutover timestamp and field-ownership map.

Variations are sellable product records locally when they have distinct SKU/barcode/stock.

### 9.3 Incoming events

Use WooCommerce webhooks for relevant order created/updated/refunded/cancelled and product events supported by the installed WooCommerce version. For every webhook:

1. capture the raw request body;
2. verify the WooCommerce signature before processing;
3. persist/deduplicate the delivery or stable event identity;
4. acknowledge promptly;
5. process through a queued idempotent job;
6. fetch the current authoritative resource if event payload completeness/order is uncertain;
7. upsert the unified local order and apply only missing state/ledger transitions.

Never trust delivery order. A stale webhook must not move a local projection backwards.

### 9.4 Outgoing stock

After a committed ledger posting, emit a stock-changed application event and queue one sync job per affected product. The job sends Laravel’s current balance, not the original delta, so retries and coalescing converge. Clamp/translate negative availability according to the WooCommerce API contract while preserving the true local balance.

Store last attempt, success, external response identifier/status, and retryable error. Use bounded exponential backoff with jitter. Authentication/validation errors require operator attention rather than infinite retries.

### 9.5 Reconciliation

Webhooks are the fast path, not the only path. Run periodic reconciliation (initially every 15 minutes, configurable) using modified-since/cursor pagination with overlap to handle clock and boundary issues. It must:

- find missing/changed WooCommerce orders and refunds;
- re-run idempotent imports;
- detect product mapping failures;
- compare expected local stock projection with WooCommerce stock;
- repair WooCommerce projection from Laravel where safe;
- expose failures and last successful run to administrators.

## 10. Kotlin/Sunmi application architecture

Use a single Android application module unless the fresh project already has a useful convention. Follow current Android recommendations available to the selected stable toolchain: Kotlin, coroutines, lifecycle-aware state, and a clear UI/data boundary. Do not create a multi-module clean-architecture framework for V1.

Suggested feature areas:

- authentication/device registration;
- POS product lookup, cart, and manual line;
- checkout/payment status;
- receipt printing;
- orders and returns;
- settings/diagnostics.

A repository/API client may coordinate HTTPS calls. Keep server DTOs separate from mutable UI state only where that separation provides a real boundary. Persist only the minimum sensitive data. Use Android secure storage for tokens and platform network-security defaults; production traffic is HTTPS only.

### 10.1 Initial screens

```text
Login / device activation
        ↓
POS: scan • search • cart • manual item
        ↓
Checkout: cash | card
        ↓
Confirmed order / payment status
        ↓
Receipt print / reprint

Secondary: Orders • Returns • Settings/Printer test
```

The cart remains local until checkout, but all submitted product IDs, quantities, prices, and VAT are revalidated/recalculated by Laravel. Disable accidental repeated submit while still relying on server idempotency.

### 10.2 Sunmi scanner adapter

Hide the vendor-specific scanner SDK/broadcast/service behind one small interface used by the POS screen. Determine the exact mechanism from the target Sunmi model, OS build, and official SDK documentation; confirm with a physical device early.

Scanner events must be lifecycle-safe, trimmed/normalized conservatively, debounced only for hardware duplicate events, and never interpreted as numbers. Camera scanning is out of V1 unless the device lacks the required scanner API.

## 11. Barcode and manual-item flows

### 11.1 Barcode

```text
hardware scan
→ app receives barcode text
→ GET product by exact barcode
→ active product returned with current price/VAT/stock
→ existing cart line quantity increments, otherwise line is added
→ unavailable/unknown product produces a clear message and manual search option
```

At checkout the server rejects inactive, unmapped, unavailable, or insufficient-stock catalog items with structured field/item errors. Scanning the same valid barcode twice increments quantity by one. Barcodes preserve leading zeroes.

### 11.2 Manual/open item

Required input:

- description;
- positive quantity;
- non-negative unit price including VAT;
- VAT rate selected from 9% or 21%.

It becomes an immutable order item with `product_id = null`. It is searchable only within that order, creates no catalog product, has no barcode, and never affects inventory or WooCommerce stock.

## 12. Checkout and MultiSafepay

### 12.1 Common checkout contract

The app creates a UUID idempotency key before the first submit and reuses it for every retry of that logical checkout. A changed cart requires a new key. Laravel returns the existing resource for a repeated equivalent request and a conflict for the same key with a different payload.

The server:

1. authenticates the user/device;
2. validates and reprices items;
3. calculates/stores VAT snapshots;
4. creates or returns the order atomically;
5. follows the selected payment state flow.

### 12.2 Cash flow

```text
POST checkout with method=cash and idempotency key
→ atomic paid order + cash payment + inventory entries
→ committed response with receipt data
→ Woo stock jobs dispatched
→ app prints receipt
```

No MultiSafepay API/SDK call is made anywhere on the cash path. If receipt printing fails, the sale remains valid and the app offers retry/reprint; it must not create another sale.

### 12.3 Card flow

```text
POST checkout with method=card
→ pending-payment order and payment attempt
→ server creates idempotent MultiSafepay transaction
→ app follows the supported terminal/payment interaction
→ signed/provider-authenticated notification and/or server status lookup
→ Laravel verifies final provider status
→ one atomic transition to paid + inventory posting
→ app polls/refreshes server state and prints only after paid
```

The exact MultiSafepay product/terminal flow must be confirmed against the merchant account, target Sunmi hardware, and current official MultiSafepay docs before implementation. Do not assume a browser checkout is equivalent to an in-person terminal transaction.

Rules:

- The Android app cannot mark a payment paid.
- Do not store PAN, CVV, track data, or other cardholder data.
- Verify webhook authenticity using the provider’s current documented mechanism and fetch transaction status server-to-server when required.
- Duplicate/out-of-order callbacks are harmless.
- A failed/cancelled/expired attempt leaves an unpaid/cancelled order with no stock posting and may allow a new payment attempt.
- A provider success that arrives after an app timeout still completes exactly once.
- Define an operator-visible recovery path for “provider paid, local transition failed” through webhook retry/reconciliation.

## 13. Wi-Fi ESC/POS printing

The Sunmi device connects directly to the printer’s LAN address, normally TCP port 9100 when supported. Confirm the specific printer protocol, encoding/code page, paper width (58/80 mm), character support, and cash-drawer behavior with real hardware.

Use one minimal printer boundary with a single network implementation, for example:

```text
ReceiptPrinter
  printSale(receipt)
  printReturn(receipt)
  testPrint()

EscPosNetworkPrinter(host, port, paperWidth, encoding)
```

Laravel returns structured receipt data; Kotlin renders deterministic ESC/POS bytes. The sale receipt includes shop identity/configured legal details, order number/date, cashier, line descriptions/quantities/prices, totals excluding VAT, VAT 9%, VAT 21%, total including VAT, payment method, and return/reference information as applicable.

A return receipt uses negative quantities and amounts and references the original order where known. Reprints say `COPY`/`REPRINT` and retain the original order/return number and timestamp.

Printing requirements:

- configurable host, port, paper width, and safe connect/write timeout;
- test-print action;
- clear errors for unreachable printer/invalid configuration;
- retry/reprint without replaying payment or order creation;
- no claim of delivery merely because bytes were queued; show the operational limitation;
- accessible text sizes/contrast and clear cashier recovery prompts.

## 14. Unified orders and VAT reporting

### 14.1 Order list

One endpoint/read model returns both sources with filters for source, status, payment method/status, order number, and date range. Each result clearly labels `Store` or `WooCommerce`. Order detail shows immutable line/VAT/payment/return history and integration status where relevant.

### 14.2 Reports

Support presets for month, quarter, year, and a custom inclusive local-date range. The response/export contains at least:

- revenue excluding VAT;
- VAT at 9%;
- VAT at 21%;
- total VAT;
- total including VAT;
- separate gross sales and returns plus net totals;
- optionally order count when trivial to include.

Use stored order/return item snapshots. Do not use current product tax rates. Default export is CSV with UTF-8 encoding and stable column names; spreadsheet/PDF formats are later requirements unless explicitly requested. CSV values must be safely escaped against spreadsheet formula injection for user-entered descriptions.

## 15. Returns and negative receipts

### 15.1 Store return

1. Find original order whenever possible.
2. Select original items and quantities.
3. Choose/record restock disposition and reason.
4. Server validates paid/completed original order and remaining returnable quantity.
5. Server creates the return, negative financial snapshots, refund record/status, and positive ledger entries for restocked quantity atomically.
6. Dispatch Woo stock sync after commit.
7. Print negative receipt.

Cash refund handling and card refund support must be explicitly connected to the original payment method. If automated MultiSafepay refunds are not part of the agreed MVP, the system must not falsely report them as refunded; use a clear pending/manual status and audit the operator action.

### 15.2 WooCommerce return/refund

Import each external refund exactly once. Map external refund lines to original imported order items, store external financial amounts, and apply stock only when the external refund/restock semantics confirm it. Ambiguous/unmapped events enter an operator-visible error state; do not guess.

### 15.3 Order state after return

- some quantity/value returned: `partially_returned`;
- all returnable quantity/value returned: `returned`;
- payment refund status is tracked separately from merchandise return status.

## 16. Purchase-order advice

V1 is a calculated report, not a persisted purchase order:

```text
include when stock_on_hand < minimum_stock
recommended_quantity = minimum_stock - stock_on_hand
```

Group by supplier and show product, article number, current stock, minimum stock, and recommended quantity. Products without a supplier appear in `Unassigned`. Include negative stock correctly. Allow CSV export if reporting exports are in the current phase. Do not add forecasting, lead time, desired stock, or automatic supplier communication.

## 17. Offline, idempotency, concurrency, and errors

### 17.1 Offline policy

V1 is **online-required for checkout**. The app may retain the current cart and checkout idempotency key across a temporary network failure, but it must not declare a sale/payment successful without the Laravel response. Card payment is never offered as an offline authorization.

On timeout after submit, the app queries/retries with the same key. It must show `status unknown—checking` rather than ask the cashier to create a second sale. A full offline sales queue is deferred because price, stock, payment, and conflict rules require a separate product decision.

### 17.2 Idempotency scopes

- POS checkout: unique device/client UUID plus payload fingerprint.
- store return: unique client UUID plus payload fingerprint.
- MultiSafepay transaction: stable local payment ID/request key.
- provider webhook: unique provider delivery/transaction state transition.
- Woo order/refund: unique external order/refund and line identity.
- inventory: unique reference/type or source-event key.
- Woo stock push: send current balance; retries converge.

### 17.3 API error shape

Return a consistent JSON envelope containing a stable machine code, human-safe message, field/item details where relevant, and request correlation ID. Distinguish validation/conflict/authentication/authorization/not-found/rate-limit/server errors with appropriate HTTP status codes. Never expose stack traces, credentials, provider secrets, or raw sensitive payloads.

### 17.4 Concurrency

Use database uniqueness constraints in addition to application checks. Lock affected product rows in consistent order for multi-line stock transactions. State transitions use conditional updates/locks so duplicate workers cannot both post inventory or mark the same payment twice.

## 18. API endpoint plan

Exact URI/version conventions may follow the fresh project, but expose a versioned API such as `/api/v1`.

### Authentication/device

```text
POST   /auth/login
POST   /auth/logout
GET    /me
POST   /devices/activate          (only if activation flow is selected)
POST   /devices/heartbeat         (optional operational need)
```

### Products/inventory

```text
GET    /products?search=&active=
GET    /products/{id}
GET    /products/by-barcode/{barcode}
POST   /products                  (manager/admin)
PATCH  /products/{id}             (manager/admin)
GET    /products/{id}/inventory-transactions
POST   /products/{id}/stock-adjustments
GET    /purchase-order-advice
GET    /purchase-order-advice/export
```

For barcode lookup, URL-encode safely or use an exact query parameter if barcode characters make a path unsafe.

### Checkout/payments

```text
POST   /checkouts                  Idempotency-Key required
GET    /orders/{order}/payment-status
POST   /orders/{order}/payment-attempts  if retry flow requires it
GET    /payments/{payment}
```

### Orders/returns/receipts

```text
GET    /orders
GET    /orders/{order}
GET    /orders/{order}/receipt
POST   /orders/{order}/returns     Idempotency-Key required
GET    /returns/{return}
GET    /returns/{return}/receipt
```

### Reporting

```text
GET    /reports/vat?from=&to=
GET    /reports/vat/export?from=&to=&format=csv
```

### Server webhooks (not app-auth endpoints)

```text
POST   /webhooks/woocommerce
POST   /webhooks/multisafepay
```

Webhook routes require provider verification, replay protection/deduplication, size limits, safe logging, and rate controls compatible with provider retries. Admin CRUD may be server-rendered Laravel pages or a small admin UI; do not duplicate every admin action in the Android app.

## 19. Background jobs and domain events

Use framework-native events/jobs only where work crosses an external boundary or is genuinely asynchronous.

Suggested internal events:

- `InventoryChanged(productId)`;
- `OrderPaid(orderId)`;
- `ReturnCompleted(returnId)`.

Suggested queued jobs:

- `SyncProductStockToWooCommerce`;
- `ProcessWooCommerceWebhook`;
- `ReconcileWooCommerceOrders`;
- `ReconcileWooCommerceStock`;
- `ProcessMultiSafepayNotification` or server status verification;
- optional batched CSV export only if synchronous exports become too large.

Use after-commit dispatch. Jobs must be retry-safe and have bounded attempts/backoff, timeouts, structured failure logging, and a failed-job/operator recovery process. Do not queue local database work that must be atomic with checkout.

## 20. Security and authentication

- HTTPS only outside local development; reject insecure production API traffic.
- Use Laravel’s supported first-party token/session mechanism appropriate for a first-party Android client. Tokens are revocable, scoped if supported, stored securely on Android, and never logged.
- Require authenticated active user and active device for POS operations.
- Enforce authorization server-side: cashiers sell/view permitted orders and create permitted returns; managers adjust stock/report; admins manage products, integrations, users, and devices.
- Rate-limit login, barcode/product search reasonably, checkout, and public webhook endpoints without breaking provider retries.
- Validate every request and integration payload. Escape output and use parameterized ORM/query APIs.
- Verify WooCommerce and MultiSafepay webhook authenticity from the raw body/current provider contract.
- Keep credentials in environment/secret management. Provide `.env.example` names with dummy values only.
- Never store or log cardholder data. Reduce PCI scope by using MultiSafepay-hosted/certified flows and SDKs.
- Apply Android release signing, disable debug behavior/logging in production, and use current platform network-security configuration.
- Back up the database and test restore procedures before go-live.
- Follow applicable fiscal, receipt-retention, privacy, and Dutch/EU accounting requirements after review by the customer/accountant; this engineering spec is not legal advice.

## 21. Logging, auditing, and operations

Use a correlation/request ID from Android through Laravel logs and relevant integration metadata. Structured logs include event name, safe internal IDs, result, duration, and error classification—never secrets, tokens, raw card data, or unnecessary personal data.

Audit at minimum:

- product price/VAT/identifier changes;
- stock adjustments/counts and reasons;
- order/payment state changes;
- returns/refunds and restock decisions;
- receipt reprints;
- integration configuration/mapping changes;
- user/device activation and administrative actions.

Provide an operational view or commands for failed Woo syncs, failed payment reconciliation, webhook processing failures, negative stock, ledger-balance mismatch, printer configuration health, and last successful reconciliation. Configure alerting for failures that require human action.

## 22. Testing strategy

### 22.1 Laravel automated tests

Unit/domain tests:

- inclusive VAT calculations and rounding for 9%/21%;
- partial-return allocation closes to original totals;
- PO advice including zero/negative stock;
- permitted order/payment state transitions.

Database/feature tests:

- cash checkout atomically creates paid order/payment/ledger and never invokes MultiSafepay;
- duplicate checkout key returns one order and one set of ledger entries;
- same key with different payload conflicts;
- concurrent sale cannot oversell allowed stock;
- manual item has no product/ledger effect;
- card success callback posts stock exactly once; failure posts none;
- webhook signature rejection and duplicate/out-of-order delivery;
- Woo order/refund import idempotency;
- return limits, negative totals, restock vs non-restock;
- unified order filters and VAT report includes negative returns/excludes unpaid/cancelled;
- authorization for cashier/manager/admin;
- inventory sum equals cached balance after each workflow.

Use provider fakes/HTTP fakes and committed example payload fixtures that contain no secrets or personal data.

### 22.2 Android automated tests

- barcode event adds/increments the correct cart line;
- unknown/inactive barcode error state;
- manual item validation;
- cart survives lifecycle/configuration changes appropriate to the UI framework;
- retry reuses checkout key and does not create a new logical checkout;
- card UI waits for server-paid status;
- receipt byte/layout builder for representative sale and return data;
- printer/network failure leaves confirmed order reprintable.

### 22.3 Integration and hardware tests

Before pilot, test on the exact Sunmi model and production-equivalent printer/network:

- 50+ repeated scans, leading-zero and unknown barcodes;
- cash sale end to end;
- MultiSafepay approved, declined, cancelled, timeout, delayed callback, duplicate callback;
- Wi-Fi loss before submit, after submit, during payment polling, and during print;
- printer offline, wrong IP, paper out (where detectable), accented currency/shop text, 58/80 mm layout;
- Woo webhook missed/duplicated/out of order plus reconciliation repair;
- simultaneous POS and Woo sale for last stock;
- full and partial returns from both sources;
- report/export totals checked against hand-calculated examples.

### 22.4 Go-live verification

Run migrations on a production-like backup/clone, perform database restore rehearsal, verify queue scheduler/worker supervision, rotate/test credentials, confirm webhook endpoints, print legal receipt samples, and reconcile an agreed sample period with WooCommerce/accounting expectations.

## 23. Phased implementation roadmap

Each phase ends with runnable tests, a demo, and updated setup notes. Do not begin payment/return complexity before the foundational invariants pass.

### Phase 0 — discovery and decisions

- inspect the customer reference without modifying it;
- confirm Sunmi model/OS/scanner API;
- confirm printer model/protocol/paper width;
- confirm MultiSafepay merchant product and in-person payment flow;
- confirm price/purchase-price tax interpretation, currency, shop timezone, negative-stock policy, return/restock rules, and Woo catalog ownership;
- produce reference gap analysis and data-migration/cutover notes.

### Phase 1 — Laravel foundation

- fresh Laravel project, database, auth/roles/devices;
- core product, tax, supplier, brand, order/payment, inventory tables;
- inventory posting service with invariants and tests;
- basic product/inventory admin and API.

### Phase 2 — Kotlin app shell and product lookup

- fresh Kotlin Android app for target Sunmi;
- secure login/session and API error handling;
- scanner adapter, barcode lookup/search, cart, manual line;
- device/printer settings shell.

### Phase 3 — MVP cash vertical slice

- idempotent cash checkout and server VAT repricing;
- paid order, cash payment, ledger posting, order detail;
- ESC/POS sale receipt and reprint;
- stock-change job plus minimal Woo product mapping/outbound sync;
- MVP acceptance demonstration.

### Phase 4 — complete WooCommerce synchronization

- signed webhooks, idempotent order/refund import;
- unified Woo order display;
- periodic order/stock reconciliation and operational failures view;
- initial import/cutover tooling.

### Phase 5 — MultiSafepay card payments

- confirmed merchant/device flow;
- payment attempts, provider integration, verified callbacks/status lookup;
- app payment status/recovery;
- reconciliation and failure tests.

### Phase 6 — returns and negative receipts

- store partial/full returns, restock decision, refund statuses;
- Woo refund mapping;
- negative receipt/reprint and stock sync.

### Phase 7 — reports and PO advice

- unified filters;
- VAT period summaries and safe CSV export;
- supplier-grouped PO advice/export.

### Phase 8 — hardening and rollout

- performance/index review from real query plans;
- audit/monitoring, backup/restore, worker/scheduler operations;
- network and hardware failure drills;
- user acceptance test, training/runbook, staged pilot and reconciliation.

## 24. MVP definition

MVP is complete only when all are true:

- an authenticated cashier on the target Sunmi scans an existing barcode and sees the correct product;
- the cashier can also add a validated manual/open line;
- Laravel recalculates VAT/totals and rejects insufficient stock;
- cash checkout creates exactly one paid order, cash payment, and ledger effect under retries;
- a compliant sale receipt prints over Wi-Fi and can be reprinted without duplicating the sale;
- Laravel stock is correct and the queued WooCommerce stock projection succeeds or exposes a recoverable failure;
- the order appears in the order list with correct VAT snapshots;
- automated tests cover VAT, cash idempotency, inventory integrity, manual item behavior, and print-failure recovery;
- the flow is demonstrated on the actual Sunmi and printer hardware.

Card payment, complete incoming Woo order/refund import, returns, final reporting, and PO advice are required for V1 but follow the vertical-slice MVP.

## 25. Coding standards and repository rules

### Laravel

- follow the project’s formatter/static-analysis/test conventions;
- use typed PHP supported by the selected Laravel/PHP versions;
- controllers validate/authorize/translate HTTP; domain actions/services hold transactional rules;
- use database constraints, foreign keys, indexes, enums/check constraints where portable and useful;
- avoid repository interfaces over Eloquent unless a real second implementation exists;
- keep external clients behind small boundaries that can be faked in tests;
- migrations are reversible where safe; never edit a deployed migration—add a new one;
- prevent N+1 queries and paginate lists/exports appropriately.

### Kotlin/Android

- follow official Kotlin style and the project formatter/linter;
- use structured concurrency; no blocking network/printer work on the main thread;
- lifecycle-aware collection/state and explicit loading/empty/error/retry states;
- keep SDK-specific Sunmi/printer code localized;
- provide accessibility basics: labels, touch targets, contrast, non-color-only errors;
- never log auth tokens, payment secrets, or sensitive responses.

### General

- prefer the smallest implementation that satisfies the current acceptance criteria;
- no speculative abstraction, microservice, event store, generic sync engine, or new dependency;
- document environment variables and local setup without secrets;
- use clear commit-sized changes and keep generated/build artifacts out of version control;
- every non-trivial branch/transaction/integration behavior leaves a runnable automated check.

## 26. Customer reference project: `Herd/voorraad-kassa`

The customer-created project is **read-only reference material**, not the new codebase or architecture source.

Rules:

1. Do not initialize the fresh project inside it, modify it, run destructive commands in it, or connect its code directly to production.
2. Do not copy code, migrations, secrets, credentials, personal/customer data, generated files, or dependencies blindly.
3. Use it only to understand terminology, workflows, UI expectations, receipt layout, existing mappings, and business edge cases.
4. Before any selective reuse, verify ownership/license, security, correctness, current framework compatibility, tests, and whether reuse is actually smaller than rewriting.
5. Record findings in a concise gap analysis: observed behavior, useful concepts/assets, unsafe/obsolete parts, missing requirements, and migration/mapping implications.
6. Reimplement required behavior in the fresh Laravel/Kotlin projects under this specification. Do not preserve a bad schema or rule merely because the reference contains it.
7. Treat `.env`, databases, logs, keys, and real order/customer data as sensitive. Do not paste them into prompts, fixtures, commits, or logs.
8. When behavior is unclear or contradicts this file, raise a documented product decision rather than guessing.

## 27. Acceptance criteria

### Products and stock

- authorized staff can create/edit the required product fields with validation and unique SKU/barcode/Woo mappings;
- only 9% or 21% active VAT can be selected in V1;
- opening/adjusted/sold/returned stock has an immutable ledger trail;
- cached stock equals ledger sum and concurrent POS sales cannot oversell under the configured rule;
- every committed stock change queues a convergent Woo stock update and failures are visible.

### WooCommerce

- valid signed events import/update orders/refunds idempotently; invalid signatures are rejected;
- duplicate and out-of-order delivery creates no duplicate order, return, or inventory effect;
- scheduled reconciliation recovers a missed event and reports mapping failures;
- POS stock changes reach the mapped Woo product/variation without creating a sync loop.

### Sunmi POS and barcode

- the target device can authenticate, scan/search products, increment duplicate scans, and handle unknown/inactive barcodes clearly;
- checkout uses the server’s accepted product, stock, price, and VAT data;
- a manual item requires description/quantity/price/VAT, appears on the order/receipt, and never changes inventory.

### Payments

- cash creates no MultiSafepay request and completes exactly once under retry;
- card uses the agreed MultiSafepay in-person flow, and only verified server-side success can mark paid/post stock;
- decline, cancellation, expiry, delayed success, duplicate callback, and network timeout produce correct recoverable states;
- no prohibited cardholder data is stored or logged.

### Printing

- sale and negative receipts print legibly on the configured Wi-Fi printer with correct identifiers, items, excluding-VAT totals, VAT 9%, VAT 21%, total, and payment/refund context;
- print failure never rolls back or duplicates a confirmed transaction;
- reprint is available, marked as a copy, and auditable.

### Orders, VAT, and returns

- one list contains store and Woo orders with correct source/status/date filters;
- month, quarter, year, and custom-period results equal stored line snapshots and include completed negative returns;
- CSV export contains correct excluding-tax, 9% VAT, 21% VAT, and totals and is safe for spreadsheet opening;
- partial/full returns cannot exceed sold quantities, use original price/VAT, track refund separately, and restore only approved restock quantity;
- Woo refunds create one mapped local return or a visible exception if safe mapping is impossible.

### PO advice and operations

- every active product below minimum appears with `minimum - current` advice, grouped by supplier, including negative stock and an unassigned group;
- logs/audit identify who/what changed price, VAT, stock, payment/order state, and returns without leaking secrets;
- backup restore, queue/scheduler operation, failed-job recovery, and reconciliation are documented and tested before go-live.

## 28. Required kickoff decisions

Resolve these during Phase 0 and append the agreed answers here or in an ADR:

- exact Sunmi model, Android/firmware version, and scanner integration method;
- exact printer model, IP assignment, port/protocol, paper width, encoding, and legal receipt fields;
- MultiSafepay merchant product, terminal/device support, sandbox account, refund scope, and callback contract;
- whether selling prices are VAT-inclusive (assumed yes) and purchase prices are excluding or including VAT;
- shop legal name/details, currency (assumed EUR), timezone, order-number/receipt-number rules, and data-retention requirements;
- WooCommerce version, credentials/webhook capability, product/variation mapping, current stock owner, initial import range, and cutover window;
- which Woo order statuses deduct stock and which refund/restock flags restore it;
- whether POS negative stock is forbidden (assumed yes) and who may adjust stock;
- whether card/cash refunds must be executed automatically in V1 or tracked as pending/manual;
- confirmation that Demand 11 means a one-sale manual/open line rather than creation of a permanent product.

Until these decisions are resolved, implement only work that is unaffected by them and use the explicit assumptions in this file. Do not hide assumptions in code.

---

## Coding-agent start prompt

Use this when beginning work in the fresh repository:

> Read `POS_PROJECT_SPEC.md` completely. Inspect the repository and report the current phase, existing conventions, and unresolved kickoff decisions. Then implement only the smallest next roadmap slice whose requirements are decided. Preserve Laravel as the business and inventory authority, put all stock changes through the ledger, and make transactional/integration operations idempotent. Add the smallest tests that prove the slice. Do not use or modify `Herd/voorraad-kassa` except as read-only behavioral reference, and do not copy secrets or customer data. If a decision changes money, stock, payment, return, or synchronization semantics, stop and state the decision needed before coding that part.
