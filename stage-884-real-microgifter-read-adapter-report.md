# Stage 884 Real Microgifter Read Adapter Connection

Built from Stage 883 Read-only Microgifter Adapter Wiring.

## Goal

Move from fixture-only adapter readiness to a real read data source by exposing live Training Lab database rows through Microgifter-style read adapter functions.

## Added

- `includes/training-lab-stage884-real-read-adapter.php`
- `includes/training-lab-stage884-real-read-render.php`
- Stage 884 summary route through `/api/training/microgifter-adapter-sync.php?section=readonly`
- Stage 884 aliases through `/api/training/microgifter-adapter-sync.php?section=real-read`, `stage884`, and `db-read`
- Stage 884 admin rendering on `/admin/adapter-readiness.php`

## Real read functions provided

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

## Data source

The first real data source is the existing Training Lab database, using read-only SELECT queries against the already-present tables:

```text
training_campaigns
training_campaign_tasks
training_participants
training_proof_submissions
training_reviews
training_reward_rules
training_reward_events
```

No new tables or migrations are required.

## Safety boundaries

- No new SQL migrations.
- No config files moved or overwritten.
- No hard auth gates forced.
- No payment processing.
- No wallet balance mutation.
- No production claim/redeem mutation.
- No destructive Microgifter sync.
- No reward issuing.
- Read-only database queries only.

## Next step

Use the DB-backed adapter output to drive the first real Training Lab operating workflow while keeping all production mutation closed.
