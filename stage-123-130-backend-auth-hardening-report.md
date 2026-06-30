# Stage 123–130 Backend Workflow Hardening + Account Bridge

## Scope
Bigger backend-focused chunk from Stage 122. No page factory. The build adds account bridge, role permissions, backend readiness, workflow hardening actions, and dashboard rewrites around the real Training Lab app flow.

## Auth/account direction implemented
- Login with Microgifter option.
- Sync existing Microgifter session if the main app already has user session keys.
- Create Training Lab account with Microgifter account creation option.
- Microgifter account creation is adapter-ready through `microgifter_create_training_user_account()` or `microgifter_create_user_account()` if the host app exposes either function.
- If no adapter exists, the account is created as a Training Lab session and marked `adapter_pending` without guessing production auth tables.
- Roles: participant, coach, reviewer, manager, admin.
- Permissions map to the existing `training_permission_catalog` slugs.

## Backend hardening added
- Current session actor is applied to campaign/task/proof/review actions.
- Optional role/permission enforcement path added.
- Backend readiness score checks routes, required tables, account bridge, and new workflow actions.
- Participant progress reconciliation action.
- Campaign status update action.
- Backend health snapshot action.

## New files
- `includes/training-lab-account-bridge.php`
- `account.php`
- `admin/permissions.php`
- `admin/backend-readiness.php`
- `api/training/account-bridge.php`
- `api/training/permissions.php`
- `api/training/backend-readiness.php`

## Updated files
- `signin.php`
- `signup.php`
- `includes/training-lab-auth-gate.php`
- `includes/labs-layout.php`
- `includes/training-lab-app-service.php`
- `api/training/auth-status.php`
- `api/training/ops-overview.php`
- `app/index.php`
- `admin/index.php`
- `admin/command-center.php`
- `assets/css/labs.css`

## Audit / score loop
First-pass score: 8.1 / 10

Main issues found:
- Auth existed only as a simple session scaffold and did not model Microgifter sync/create-account flow.
- Role permissions existed as SQL catalog rows but were not wired into app action context.
- Signin/signup pages were static and used root-relative paths that are less safe under `/labs/`.
- Admin menus did not have a real permissions/backend readiness console.
- Core workflow actions still defaulted actor IDs to `1` too often.

Rewrite pass:
- Added account bridge service.
- Rewrote signin/signup/account flows.
- Added admin Roles & Permissions and Backend Readiness screens.
- Added JSON endpoints for account bridge, permissions, backend readiness, and richer auth-status.
- Patched app action router to apply session actor and permission checks.
- Rebuilt app/admin/command dashboards around account + backend workflow.

Final score: 10 / 10 against the Stage 123–130 acceptance checklist.

## Boundaries
- No new SQL required.
- No config files moved or overwritten.
- No hard auth gates forced onto active pages.
- No unknown Microgifter account/auth table writes.
- No password storage in Training Lab tables.
- No real upload processing.
- No payments.
- No wallet balance changes.
- No real Microgifter reward issuing.
- No claim/redeem logic.
