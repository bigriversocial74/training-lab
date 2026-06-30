# Stage 14 — Read-Only Campaign Inspector

Source base: `training-lab-stage13-readonly-admin-command-center-full.zip` working tree.

## Scope

Added a visible, read-only campaign inspector so David can drill into one campaign after the Stage 13 command center.

## Files changed

- `admin/campaign-inspector.php` — new campaign-level read-only admin page.
- `api/training/campaign-inspector.php` — new read-only JSON contract for the same inspector data.
- `admin/index.php` — visible Stage 14 callout and primary link.
- `admin/command-center.php` — links into Campaign Inspector and updates next-safe-path copy.
- `admin/campaigns.php` — DB-aware campaign rows with Inspect links.
- `api/training/ops-overview.php` — includes the campaign inspector payload and Stage 14 metadata.
- `includes/labs-layout.php` — sidebar link for Campaign Inspector.
- `includes/training-lab-stage34-service.php` — read-only campaign inspector service helpers.
- `assets/css/labs.css` — Stage 14 UI styles.

## Read-only data shown

- Selected campaign details.
- Task sequence.
- Participants.
- Proof submissions.
- Review decisions.
- Action receipts.
- Reward rules.
- Reward events.
- Event/timeline preview.

## Safety boundaries

- No auth gates added.
- No campaign/task/review writes.
- No proof upload processing.
- No payments.
- No wallet balance changes.
- No Microgifter reward issuing.
- No claim/redeem logic.
- No duplicate auth system.
- No config files moved or overwritten.

## SQL

No new SQL required.
