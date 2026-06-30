# Training Lab Stage 3/4 Build Report

## Build Boundary

This checkpoint builds Stage 3 and Stage 4 as far as possible without crossing into real SQL, real uploads, payments, wallet writes, claim/redeem logic, or production deployment.

David will provide SQL context after a few sections. When SQL starts, the build should produce one consolidated SQL file for the reviewed section, not scattered one-off migrations.

## Stage 3 — Backend Integration Scaffold

Score before pass: 8/10

Fixes built:
- Added shared read-only service scaffold: `includes/training-lab-stage34-service.php`
- Added centralized seed data for campaigns, tasks, reviews, wallet preview, dashboard metrics, and integration map
- Added API contract endpoints under `api/training/`
- Added account/wallet/reward ownership boundary mapping
- Removed page-by-page hard-coded drift from app/admin pages where practical

Files added:
- `labs/includes/training-lab-stage34-service.php`
- `labs/api/training/bootstrap.php`
- `labs/api/training/campaigns.php`
- `labs/api/training/campaign-detail.php`
- `labs/api/training/review-queue.php`
- `labs/api/training/wallet-preview.php`
- `labs/api/training/integration-map.php`

Rescore after pass: 9.4/10

Remaining to reach 10/10:
- Review existing Microgifter SQL/table names once David provides SQL/context
- Confirm actual user, merchant/organization, wallet, reward, claim, and role table mappings
- Convert this service scaffold from static arrays to repository-backed reads after SQL approval

## Stage 4 — Read-Only Implementation Roadmap Built Into UI

Score before pass: 7/10

Fixes built:
- Stage 4A read-only backend-style data now powers app dashboard, campaigns, campaign detail, tasks, rewards, wallet, admin overview, admin campaigns, and review queue
- Added JSON endpoints that match the future backend contract
- Preserved localStorage demo state for Submit Proof / Approve Demo / Reset Demo
- Added explicit UI boundaries around reward/wallet/review write actions
- Kept all write paths demo-only

Pages updated:
- `labs/app/index.php`
- `labs/app/campaigns.php`
- `labs/app/campaign-detail.php`
- `labs/app/sequence-tasks.php`
- `labs/app/rewards.php`
- `labs/app/wallet.php`
- `labs/admin/index.php`
- `labs/admin/campaigns.php`
- `labs/admin/review-queue.php`

Rescore after pass: 9.2/10

Remaining to reach 10/10:
- SQL/account inspection
- Repository reads against real tables
- Permission checks
- Upload policy confirmation
- Reward/wallet contract confirmation

## Still Clean

- No SQL file created in this checkpoint
- No database connection
- No database writes
- No real file uploads
- No payments
- No wallet balance changes
- No reward issuing
- No claim/redeem logic
- No separate account system
- No production hosting/DNS changes
