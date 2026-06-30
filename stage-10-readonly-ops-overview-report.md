# Training Lab Stage 10 Read-Only Ops Overview Report

## Scope

This stage continues from David's current `examples/labs/` codebase. It keeps the existing database config placement intact and adds read-only operational visibility.

## Working folder

```text
examples/labs/
```

## Config placement preserved

```text
examples/labs/labs/config.php
examples/labs/labs/config-example.php
```

From:

```text
examples/labs/includes/training-lab-db.php
```

The loader still resolves:

```php
dirname(__DIR__) . '/labs/config.php'
```

## Added / updated files

```text
includes/training-lab-db.php
api/training/db-status.php
api/training/ops-overview.php
admin/db-health.php
includes/labs-layout.php
stage-10-readonly-ops-overview-report.md
```

## What changed

- Added a central required Training Lab table list.
- Added row-count checks for imported Training Lab tables.
- Added a structured `tl_db_status_summary()` helper.
- Updated `/api/training/db-status.php` to use the shared status helper.
- Added `/api/training/ops-overview.php` for dashboard, DB status, campaign, review, wallet-preview, and recent event visibility.
- Added `/admin/db-health.php` as a read-only admin page for config/table visibility.
- Added an Admin sidebar link for DB Health.

## Safety boundaries

Still disabled / not implemented:

```text
real media upload processing
payments
wallet balance writes
Microgifter reward issuing
claim/redeem logic
duplicate auth system
```

## Next stage

Stage 11 should focus on the admin campaign builder UI and controlled Stage 7 action forms, still staying inside `examples/labs/`.
