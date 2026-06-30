# Stage 883 Read-only Microgifter Adapter Wiring

Built from Stage 882 Live Environment Smoke + Microgifter Adapter Dry Run after live smoke accepted at 100/100.

## Goal

Wire real Microgifter read adapter functions into the Training Lab visibility layer without enabling any production mutation.

## Added

- `includes/training-lab-stage883-readonly-adapter.php`
- `admin/adapter-readiness.php`
- `/api/training/microgifter-adapter-sync.php?section=readonly`
- Stage 883 overlay in the adapter sync summary
- Admin nav link for Adapter Readiness
- README Stage 883 route and safety documentation

## Read-only contracts checked

- Merchant campaign catalog
- Customer awards
- Customer account status
- Adapter status
- Inventory freshness

Each contract reports:

- status
- adapter vs fixture source
- available function names
- developer-key presence
- row count
- response shape score
- fallback status

## Adapter functions probed

Campaign/catalog reads:

```text
microgifter_training_campaign_catalog
microgifter_merchant_reward_campaigns
microgifter_reward_campaign_catalog
microgifter_reward_catalog
```

Customer award reads:

```text
microgifter_training_user_awards
microgifter_customer_awards
microgifter_user_awards
```

Customer account reads:

```text
microgifter_user_account_status
microgifter_customer_account_status
microgifter_training_user_account_status
```

Adapter/sync status reads:

```text
microgifter_adapter_status
microgifter_training_sync_status
microgifter_adapter_sync_status
microgifter_campaign_sync_health
microgifter_reward_inventory_refresh_preview
```

## Safe fallback behavior

If real adapter functions are missing, missing a developer key, or return invalid shapes, Stage 883 falls back to existing Stage 800/840/880 fixture data and reports the fallback explicitly.

## Safe boundaries

- No new SQL.
- No config files moved or overwritten.
- No hard auth gates forced.
- No payment processing.
- No wallet balance mutation.
- No production claim/redeem mutation.
- No destructive Microgifter sync.
- No reward issuing.
- Read-only adapter calls only.
