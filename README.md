# Training Lab Stage 880 Baseline

This repository now contains the unpacked Stage 880 Training Lab baseline as real source files. The zip artifact was used only as the sanitized import package and is not part of the active source tree.

## Current baseline

Stage 880 is the accepted product/integration baseline on `main`. Stage 881 and Stage 882 are QA and live-smoke layers on top of that baseline. Stage 883 adds read-only Microgifter adapter wiring, Stage 884 connects the first real DB-backed read adapter source, and Stage 885 adds the first real proof-review operating workflow with award handoff preview.

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
stage-881-deployment-acceptance-route-qa-report.md
stage-882-live-smoke-adapter-dry-run-report.md
stage-883-readonly-microgifter-adapter-wiring-report.md
stage-884-real-microgifter-read-adapter-report.md
stage-885-proof-review-handoff-preview-report.md
includes/training-lab-stage880-adapter-sync.php
includes/training-lab-stage881-deployment-acceptance.php
includes/training-lab-stage882-live-smoke.php
includes/training-lab-stage883-readonly-adapter.php
includes/training-lab-stage884-real-read-adapter.php
includes/training-lab-stage884-real-read-render.php
includes/training-lab-stage885-proof-review-handoff.php
api/training/microgifter-adapter-sync.php
api/training/proof-review-workflow.php
api/training/deployment-acceptance.php
api/training/live-smoke.php
admin/db-health.php
admin/deployment-acceptance.php
admin/live-smoke.php
admin/adapter-readiness.php
admin/review-workbench.php
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
http://127.0.0.1:8091/admin/deployment-acceptance.php
http://127.0.0.1:8091/admin/live-smoke.php
http://127.0.0.1:8091/admin/adapter-readiness.php
http://127.0.0.1:8091/admin/review-workbench.php
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

Generated GitHub deploy archives intentionally omit `/config.php` and `/labs/config.php` so a fresh `main.zip` deployment does not overwrite the live private DB credentials. First-time installs should copy from `config-example.php` / `labs/config-example.php` and fill credentials on the server.

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

## Stage 881 deployment acceptance

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

## Stage 882 live smoke and adapter dry run

Stage 882 adds live environment smoke checks and read-only adapter dry-run visibility.

Human-readable Stage 882 route:

```text
/admin/live-smoke.php
```

Machine-readable Stage 882 route:

```text
/api/training/live-smoke.php
```

Stage 882 checks:

```text
Stage 881 deployment acceptance status
/admin/deployment-acceptance.php
/api/training/deployment-acceptance.php
/admin/db-health.php
/api/training/db-status.php
/api/training/microgifter-adapter-sync.php
/api/training/microgifter-adapter-sync.php?section=audit
DB config readiness
DB connection status
required Training Lab table readiness
Stage 880 adapter audit status
developer-key presence
adapter mode
mutation boundary status
```

Stage 882 read-only adapter dry run surfaces:

```text
Merchant campaign catalog
Customer awards
Identity matching
Inventory freshness
Award handoff preview
```

## Stage 883 read-only adapter wiring

Stage 883 validates Microgifter read adapter function shape while preserving safe fixture fallback.

Human-readable Stage 883/884 route:

```text
/admin/adapter-readiness.php
```

Machine-readable Stage 883 route:

```text
/api/training/microgifter-adapter-sync.php?section=stage883
```

Stage 883 checks:

```text
Merchant campaign catalog read shape
Customer awards read shape
Customer account status read shape
Adapter status read shape
Inventory freshness read shape
Developer-key presence
Fixture fallback safety
No production mutation
```

## Stage 884 real read adapter connection

Stage 884 exposes live Training Lab database rows through Microgifter-style read adapter functions.

Human-readable Stage 884 route:

```text
/admin/adapter-readiness.php
```

Machine-readable Stage 884 routes:

```text
/api/training/microgifter-adapter-sync.php?section=readonly
/api/training/microgifter-adapter-sync.php?section=real-read
/api/training/microgifter-adapter-sync.php?section=stage884
/api/training/microgifter-adapter-sync.php?section=db-read
```

Stage 884 provides read-only functions for:

```text
Merchant campaign catalog
Reward catalog
Customer awards
Customer account status
Adapter status
Inventory freshness
```

## Stage 885 proof review + award handoff preview

Stage 885 adds the first real Training Lab operating workflow around live proof rows.

Human-readable Stage 885 route:

```text
/admin/review-workbench.php
```

Machine-readable Stage 885 route:

```text
/api/training/proof-review-workflow.php
```

Stage 885 supports:

```text
Submitted proof queue
Approve / reject / needs-more-info decisions
Training Lab review writes
Training Lab receipt / reward eligibility writes after approval
Preview-only Microgifter award handoff visibility
No Microgifter issuing
No claim/redeem mutation
No wallet mutation
No payments
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
Adapter dry run remains read-only
Award handoff remains preview/control by default
Microgifter reward issuing remains adapter/developer-key gated
```

## Validation

Run the full recursive PHP syntax check:

```bash
./run-full-syntax-check.sh
```

GitHub Actions also runs the PHP syntax workflow on PRs to `main` and pushes to key branch patterns.

## Stage history

Latest accepted feature layer:

```text
Stage 885 Proof Review + Award Handoff Preview
```

QA layers after Stage 880:

```text
Stage 881 Deployment Acceptance + Route QA
Stage 882 Live Environment Smoke + Microgifter Adapter Dry Run
Stage 883 Read-only Microgifter Adapter Wiring
Stage 884 Real Microgifter Read Adapter Connection
```

Earlier reports remain in the repo for audit history. Do not use old Stage 2/5 notes as the current operating boundary; this README is now the current baseline summary.
