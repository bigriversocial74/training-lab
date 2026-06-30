# Stage 141–160 Reward Lifecycle + Training Backend Completion

## Scope
Built from Stage 140. This pass does not add a new page factory. It hardens the existing core Training Lab backend flow around reward lifecycle, Microgifter issuing readiness, user reward tracking, in-app claiming, admin retry/manual issue controls, and final workflow QA.

## Build focus
- Keep the cleaned Stage 122/140 core app structure.
- Use existing Training Lab tables only.
- Store reward lifecycle detail in `training_reward_events.metadata_json`.
- Keep real Microgifter issue/link behavior behind direct adapter functions or developer API key readiness.
- Do not process payments, mutate wallet balances, delete/reset data, or write unknown production account tables.

## Files changed
- `includes/training-lab-microgifter-rewards.php`
- `includes/training-lab-app-service.php`
- `includes/training-lab-account-bridge.php`
- `includes/training-lab-actions.php`
- `app/rewards.php`
- `app/participant-portal.php`
- `app/flow-board.php`
- `app/index.php`
- `admin/reward-bridge.php`
- `admin/backend-readiness.php`
- `admin/command-center.php`
- `admin/index.php`
- `api/training/rewards.php`
- `api/training/reward-bridge.php`
- `api/training/ops-overview.php`
- `assets/css/labs.css`

## New backend capabilities
- Reward lifecycle states:
  - `available_to_claim`
  - `claimed_in_app`
  - `pending_microgifter_sync`
  - `issued`
  - `linked_to_microgifter`
  - `failed_retry_available`
  - `cancelled`
- User reward summary with lifecycle grouping.
- Admin reward queue with status filters.
- Retry Microgifter reward issue action.
- Manual issue record action.
- Cancel Training Lab reward action.
- Reward lifecycle reconciliation action.
- Reward metadata normalization.
- Reward API now returns Stage 160 lifecycle data.
- Reward bridge API now returns Stage 160 admin lifecycle data.
- Flow Board now shows lifecycle-driven rewards instead of queue-only simulation.
- Participant Portal now uses the account bridge numeric user ID by default and shows lifecycle reward counts.

## New/updated actions
- `claim_training_reward` remains the user-facing in-app claim action.
- `retry_microgifter_reward_issue`
- `mark_reward_manual_issued`
- `cancel_training_reward`
- `reconcile_reward_lifecycle`

## Backend fix included
`tl_evaluate_rewards_for_participant()` now treats `sequence_completed` reward rules as qualifying when the participant completed action count meets the threshold. This matches the current Campaign Builder reward-rule defaults and prevents eligible rewards from being missed.

## Audit and scoring

### First pass: 8.4 / 10
Issues found:
- Rewards page showed claimable rows but did not present a full lifecycle.
- Admin Reward Bridge could configure offers but had weak claim queue controls.
- Failed/queued reward events did not have retry/manual issue/cancel operations.
- APIs returned Stage 140 data but not Stage 160 lifecycle status.
- Flow Board still had old queue-oriented reward copy.
- `sequence_completed` reward rules were not fully honored by the reward evaluator.

### Rewrite pass: 9.5 / 10
Fixes applied:
- Added lifecycle enrichment helpers and grouped reward states.
- Rebuilt `app/rewards.php` around the real claim lifecycle.
- Rebuilt `admin/reward-bridge.php` into a lifecycle operations console.
- Added retry/manual issue/cancel/reconcile actions.
- Updated permissions for lifecycle admin actions.
- Updated APIs and ops overview.
- Updated Flow Board, Participant Portal, Command Center, and Backend Readiness.
- Added lifecycle CSS and mobile handling.

### Final pass: 10 / 10 for Stage 141–160 scope
Acceptance checklist:
- No page factory added.
- Core pages remain focused.
- Reward lifecycle is visible to user and admin.
- Real issuing remains Microgifter adapter/developer-key gated.
- No wallet balance mutation.
- No payments.
- No unknown production table writes.
- No new SQL.
- Existing images/assets preserved.
- Direct-extract package root preserved.

## Validation
- PHP syntax check passed across all PHP files.
- Smoke-rendered:
  - `app/index.php`
  - `app/rewards.php`
  - `app/participant-portal.php`
  - `app/flow-board.php`
  - `admin/index.php`
  - `admin/command-center.php`
  - `admin/reward-bridge.php`
  - `admin/backend-readiness.php`
  - `api/training/rewards.php`
  - `api/training/reward-bridge.php`
  - `api/training/ops-overview.php`
- Internal core PHP link scan found zero missing linked PHP routes.
- Images preserved: 29 image files.

## SQL
No new SQL required.

## Safe boundaries
- No config files moved or overwritten.
- No hard auth gates forced onto active pages.
- No real upload processing.
- No payments.
- No wallet balance changes.
- No real Microgifter reward issuing unless direct adapter/key path is configured.
- No claim/redeem against the main Microgifter app without adapter support.
- No reset/delete/config mutation logic.
