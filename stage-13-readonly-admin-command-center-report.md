# Stage 13 Read-Only Admin Command Center Report

## Source
Built from the accepted Stage 12 direct-extract route check package.

## Scope
Stage 13 adds a more obvious read-only admin command center so David can see Training Lab state immediately after upload/extract.

## Files changed
- `admin/command-center.php`
- `admin/index.php`
- `api/training/ops-overview.php`
- `includes/labs-layout.php`
- `includes/training-lab-stage34-service.php`
- `assets/css/labs.css`
- `stage-13-readonly-admin-command-center-report.md`

## Added visibility
- Health score based on schema-ready table count.
- Campaign, task, proof, review, reward, event, and row-count visibility.
- Recent campaign table.
- Proof/review queue visibility.
- Event log preview.
- Full table health grid.
- Fast links to Route Check, DB Health, Campaigns, Reviews, and participant app.
- Command-center payload inside `/api/training/ops-overview.php`.

## Preserved boundaries
- No auth gates added.
- No config files moved or overwritten.
- No real media upload processing.
- No payments.
- No wallet balance changes.
- No Microgifter reward issuing.
- No claim/redeem logic.
- No duplicate auth system.
- No new SQL required.

## Packaging
The zip opens directly to the active Training Lab files. Upload/extract inside `examples/labs/`; do not create an extra wrapper folder.
