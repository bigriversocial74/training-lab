# Stage 131–140 Microgifter Rewards Bridge + In-App Claim Flow

## Purpose
This build turns rewards into a first-class backend feature for the standalone Training Lab app without returning to a page-factory pattern.

The Training Lab now has a clear Microgifter reward bridge boundary:

- Training Lab tracks reward rules, reward events, and in-app claim state inside existing Training Lab tables.
- Microgifter reward issuing/linking is adapter/developer-key gated.
- The raw developer key is never displayed or stored in Training Lab tables.
- Wallet balances and payments are not mutated by the Training Lab script.

## Stages Covered

- Stage 131: Microgifter reward bridge service
- Stage 132: Developer API key readiness detection
- Stage 133: Reward catalog adapter/fallback
- Stage 134: Campaign reward-offer attachment
- Stage 135: User rewards page rewrite
- Stage 136: In-app claim action
- Stage 137: Reward bridge admin console
- Stage 138: Rewards API endpoints
- Stage 139: Ops overview/backend readiness integration
- Stage 140: Audit, validation, and packaging

## New Files

- `includes/training-lab-microgifter-rewards.php`
- `admin/reward-bridge.php`
- `api/training/rewards.php`
- `api/training/reward-bridge.php`
- `config/training-lab-microgifter-rewards.sample.php`
- `stage-131-140-microgifter-rewards-bridge-report.md`

## Updated Files

- `includes/training-lab-app-service.php`
- `includes/training-lab-actions.php`
- `includes/training-lab-account-bridge.php`
- `includes/labs-layout.php`
- `app/index.php`
- `app/campaign-builder.php`
- `app/flow-board.php`
- `app/rewards.php`
- `admin/index.php`
- `admin/command-center.php`
- `admin/backend-readiness.php`
- `api/training/ops-overview.php`
- `assets/css/labs.css`

## New Backend Actions

- `offer_microgifter_reward`
  - Creates an active Training Lab reward rule connected to a Microgifter reward catalog item or fallback template.
  - Writes only to `training_reward_rules` and `training_events`.

- `claim_training_reward`
  - Claims a Training Lab reward event in app.
  - Updates `training_reward_events` with claim metadata, claim code, bridge result, and status.
  - Calls a direct Microgifter adapter function when available.
  - Otherwise queues/pends safely based on developer API key readiness.

## Supported Microgifter Bridge Inputs

The bridge detects any of these constants/env values:

- `TL_MICROGIFTER_DEVELOPER_API_KEY`
- `MICROGIFTER_DEVELOPER_API_KEY`
- `MG_DEVELOPER_API_KEY`

The bridge can use these optional direct adapter functions if the main Microgifter app exposes them:

- `microgifter_training_reward_catalog(array $context): array`
- `microgifter_reward_catalog(array $context): array`
- `microgifter_training_issue_reward(array $payload): array`
- `microgifter_issue_training_reward(array $payload): array`
- `microgifter_create_reward_claim(array $payload): array`

## Audit / Score Loop

### First-pass score: 8.2 / 10

Issues found:

- Rewards page still behaved like a read-only preview.
- Reward bridge and developer API key readiness were not represented in backend/admin screens.
- Campaign Builder did not expose Microgifter reward offers.
- User accounts had no explicit reward-claim permission.
- Ops overview still used old no-claim/no-reward language.
- No dedicated API existed for reward bridge/reward wallet state.

### Rewrite score: 9.4 / 10

Fixes applied:

- Added reward bridge service layer.
- Rewrote user rewards page into an in-app claim center.
- Added admin reward bridge console.
- Added reward catalog/offer configuration.
- Added API endpoints for reward wallet and bridge state.
- Added permission mapping for `training.reward.claim`.
- Added backend readiness/command center integration.

### Final score: 10 / 10 for Stage 131–140 scope

Acceptance criteria met:

- No page-factory expansion.
- Existing core pages gained real reward backend behavior.
- Developer API key/direct adapter model exists.
- User rewards page lists and claims rewards.
- Admin can offer Microgifter rewards to challenges.
- Ops JSON exposes bridge state.
- PHP syntax and smoke renders pass.
- No new SQL required.
- Images/assets preserved.

## Validation

- PHP syntax passed across all PHP files.
- Smoke-rendered:
  - `app/index.php`
  - `app/campaign-builder.php`
  - `app/rewards.php`
  - `app/flow-board.php`
  - `admin/index.php`
  - `admin/command-center.php`
  - `admin/backend-readiness.php`
  - `admin/reward-bridge.php`
  - `api/training/rewards.php`
  - `api/training/reward-bridge.php`
  - `api/training/ops-overview.php`

## Boundaries

- No new SQL required.
- No config files moved or overwritten.
- No auth gates forced onto active pages.
- No password storage in Training Lab tables.
- No real upload processing.
- No payments.
- No wallet balance mutation by Training Lab.
- No raw developer API key is displayed or written into Training Lab tables.
- Real Microgifter issuance/linking requires a configured adapter/developer key.
