# Stage 882 Live Environment Smoke + Microgifter Adapter Dry Run

Built from Stage 881 Deployment Acceptance + Route QA.

## Scope

Stage 882 adds read-only live environment smoke checks and Microgifter adapter dry-run visibility. It does not rebuild the app and does not enable production mutation.

## Files added

- `includes/training-lab-stage882-live-smoke.php`
- `api/training/live-smoke.php`
- `admin/live-smoke.php`
- `stage-882-live-smoke-adapter-dry-run-report.md`

## Files updated

- `README.md`
- `includes/labs-layout.php`

## Live smoke checks

Stage 882 checks:

- Stage 881 deployment acceptance status
- `/admin/deployment-acceptance.php`
- `/api/training/deployment-acceptance.php`
- `/admin/db-health.php`
- `/api/training/db-status.php`
- `/api/training/microgifter-adapter-sync.php`
- `/api/training/microgifter-adapter-sync.php?section=audit`
- DB config readiness
- DB connection status
- required Training Lab table readiness
- Stage 880 adapter audit status
- developer-key presence
- adapter mode
- mutation boundary status

## Adapter dry-run cards

The read-only adapter dry run surfaces:

- Merchant campaign catalog
- Customer awards
- Identity matching
- Inventory freshness
- Award handoff preview

## Routes

Human-readable:

```text
/admin/live-smoke.php
```

Machine-readable:

```text
/api/training/live-smoke.php
```

## Safety boundaries

- No new SQL.
- No config files moved or overwritten.
- No hard auth gates forced.
- No payment processing.
- No wallet balance mutation.
- No production claim/redeem mutation.
- No destructive sync back to Microgifter.
- Adapter dry run is read-only.
- Award handoff remains preview/control only.
