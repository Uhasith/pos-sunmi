---
paths:
  - 'app/Filament/**'
---

# Filament

## Filament is the administrative adapter
Use Filament 5 for manager/admin operations only. Keep stock_on_hand read-only, make inventory ledger records view-only, and route stock adjustments through PostInventory with actor, reason, and idempotency data. Do not build the Sunmi cashier UI in Filament.

## One back-office UI under /admin
Maintain a single human back-office in the Filament admin panel under /admin. Build CRUD as Filament resources and exceptional operational workflows as custom Filament pages or Livewire components inside that panel. Do not create a second staff dashboard; Android APIs, webhooks, and provider callbacks remain separate non-UI routes.
