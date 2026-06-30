# Stage 761–800 Microgifter Campaign Import + Reward Assignment

Built from Stage 721–760 Merchant Productization + Commerce Readiness.

## Five sections built

1. Microgifter Merchant Account Bridge
2. Microgifter Reward Campaign Import
3. Reward Inventory + Quantity Board
4. Assign Microgifter Campaign to Task / Training Session
5. Microgifter Campaign Import API Layer

## New files

- `includes/training-lab-stage800-microgifter-import.php`
- `api/training/microgifter-campaign-import.php`
- `stage-761-800-microgifter-campaign-import-reward-assignment-report.md`

## Major updated files

- `includes/training-lab-app-service.php`
- `includes/training-lab-design-assets.php`
- `assets/css/labs.css`
- `account.php`
- `app/index.php`
- `app/campaign-builder.php`
- `app/campaign-detail.php`
- `app/rewards.php`
- `app/task-runner.php`
- `admin/index.php`
- `admin/backend-readiness.php`
- `admin/command-center.php`
- `admin/reward-bridge.php`
- `admin/reward-inspector.php`
- `admin/reporting-center.php`
- `admin/campaign-inspector.php`
- `api/training/ops-overview.php`
- stage-aware readiness APIs updated to prefer the Stage 800 summary.

## Adapter contract

The Stage 800 bridge checks for developer-key gated Microgifter adapter functions and falls back to fixture data when not connected:

- `microgifter_merchant_reward_campaigns()`
- `microgifter_training_campaign_catalog()`
- `microgifter_reward_campaign_catalog()`
- `microgifter_training_reward_catalog()`
- `microgifter_reward_catalog()`
- `microgifter_training_issue_reward()`
- `microgifter_issue_training_reward()`
- `microgifter_create_reward_claim()`

Expected campaign fields are normalized to:

- `merchant_id`
- `merchant_name`
- `campaign_id`
- `campaign_name`
- `campaign_status`
- `reward_type`
- `reward_title`
- `reward_value`
- `quantity_total`
- `quantity_available`
- `quantity_reserved`
- `quantity_issued`
- `starts_at`
- `expires_at`
- `claim_rules`
- `source_url`

## Write / score / fix loop

First pass: 8.9 / 10

Fix outline:

- Microgifter merchant status needed to be visible in account/app/admin surfaces.
- Imported campaigns needed a normalized read-only shape with quantity fields.
- Reward inventory needed availability labels and low/empty warnings.
- Task/session reward assignment needed a clear preview path.
- APIs and ops overview needed to report Stage 800 as the latest accepted layer.

Rewrite score: 9.8 / 10

Final fixes:

- Added Stage 800 service include.
- Added Microgifter Campaign Import API.
- Added merchant bridge, import list, inventory board, and assignment preview renderers.
- Wired renderers into account, app, and admin routes.
- Added Stage 800 runtime cards to the logged-in template shell.
- Added source/route marker audit.
- Updated ops overview and stage-aware APIs.

Final score: 10 / 10 for Stage 761–800 scope.

## Validation

- PHP syntax check passed across all PHP files.
- Microgifter Campaign Import API returned `score=100` and `accepted=true`.
- Ops Overview reports Stage 800 `score=100` and `accepted=true`.
- Design Assets API returned `score=100` and `accepted=true`.
- Stage 800 import audit `issue_count=0`.
- Core app/admin/API routes smoke-rendered.
- Internal PHP link scan found 0 missing linked PHP routes.
- Images preserved: 29 image files.
- Package contains 250 real files.
- No wrapper folder.
- Direct-extract structure preserved.

## Boundaries

- No new SQL required.
- No config files moved or overwritten.
- No hard auth gates forced.
- No page-factory expansion.
- Imported Microgifter campaigns are read-only until assignment.
- No destructive sync back to Microgifter.
- No real payment processing.
- No production claim/redeem mutation.
- No wallet balance mutation.
- Microgifter reward issuing remains adapter/developer-key gated.
