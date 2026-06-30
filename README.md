# Training Lab Stage 880 Baseline

This repository now contains the unpacked Stage 880 Training Lab baseline as real source files. The zip artifact was used only as the sanitized import package and is not part of the active source tree.

## Current baseline

Stage 880 is the accepted baseline on `main`.

Core source folders:

```text
admin/
api/
app/
assets/
config/
database/
includes/
labs/
```

Important source-of-truth files:

```text
IMPORT-NOTES.md
stage-841-880-microgifter-adapter-sync-award-handoff-report.md
includes/training-lab-stage880-adapter-sync.php
api/training/microgifter-adapter-sync.php
admin/db-health.php
run-full-syntax-check.sh
```

## Local preview

From the repository root:

```bash
php -S 127.0.0.1:8091
```

Open:

```text
http://127.0.0.1:8091/
```

You can also preview the app/admin routes directly:

```text
http://127.0.0.1:8091/app/index.php
http://127.0.0.1:8091/admin/command-center.php
http://127.0.0.1:8091/admin/db-health.php
```

## Deployment config

The active database config path is:

```text
labs/config.php
```

The sanitized public repository must keep the password placeholder:

```text
PUT_YOUR_DATABASE_PASSWORD_HERE
```

Do not commit live database credentials.

The root `config.php` and `labs/config.php` are intentionally sanitized in the repo. On a deployed server, edit the deployed private config only.

## Database status

Training Lab expects the existing Training Lab tables:

```text
training_campaigns
training_campaign_tasks
training_participants
training_proof_submissions
training_reviews
training_action_receipts
training_reward_rules
training_reward_events
training_streaks
training_events
training_permission_catalog
```

Use this read-only admin route to verify config, connection, table presence, schema readiness, row counts, and latest events:

```text
/admin/db-health.php
```

Use this API route for machine-readable status:

```text
/api/training/db-status.php
```

## Stage 880 adapter sync

Stage 880 adds Microgifter adapter sync visibility and award handoff control.

Primary Stage 880 route:

```text
/api/training/microgifter-adapter-sync.php
```

Supported sections:

```text
/api/training/microgifter-adapter-sync.php?section=config
/api/training/microgifter-adapter-sync.php?section=identity
/api/training/microgifter-adapter-sync.php?section=sync
/api/training/microgifter-adapter-sync.php?section=handoff
/api/training/microgifter-adapter-sync.php?section=audit
```

Stage 880 covers:

```text
Microgifter Adapter Configuration Center
Merchant + Customer Identity Matching
Campaign Sync Health + Inventory Refresh
Award Handoff Queue
Adapter Sync API Layer
```

## Safe boundaries

Keep these boundaries unless a later stage explicitly changes them:

```text
No hard auth gates forced onto active app/admin pages
No config files moved or overwritten
No new SQL unless a missing schema is proven
No real upload processing
No payment processing
No wallet balance mutation
No production claim/redeem mutation without adapter/developer-key gating
No destructive sync back to Microgifter
Award handoff remains preview/control by default
Microgifter reward issuing remains adapter/developer-key gated
```

## Validation

Run the full recursive PHP syntax check:

```bash
./run-full-syntax-check.sh
```

Stage 881 adds a deployment acceptance layer that checks:

```text
required source folders
config placeholder safety
DB config path expectations
key public/app/admin/API routes
Stage 880 adapter sync API
safe mutation boundaries
recursive syntax script coverage
```

Human-readable Stage 881 route:

```text
/admin/deployment-acceptance.php
```

Machine-readable Stage 881 route:

```text
/api/training/deployment-acceptance.php
```

## Stage history

The latest accepted feature layer before Stage 881 QA is:

```text
Stage 841-880 Microgifter Adapter Sync + Award Handoff Control
```

Earlier reports remain in the repo for audit history. Do not use old Stage 2/5 notes as the current operating boundary; this README is now the current baseline summary.
