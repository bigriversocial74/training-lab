# Production Runtime Acceptance v1

Production Runtime Acceptance v1 is the post-deployment verification layer for the Training Lab. It is intentionally read-only and does not create training records, issue Microgifter rewards, process payments, mutate wallets, create claims, redeem rewards, or perform destructive synchronization.

## Protected routes

- `/admin/runtime-acceptance.php`
- `/api/training/runtime-acceptance.php`
- Add `?probe=1` to either route to run explicit same-origin live HTTP probes.

A trusted connected Microgifter manager or administrator session is required. The JSON API also accepts the existing valid developer-key authorization path.

## Acceptance groups

1. **Deployment and configuration**
   - Private `/labs/config.php` path
   - Live non-placeholder database configuration
   - Database connection and required tables
   - Stage 882 live baseline
   - HTTPS and production demo-login state
   - Required admin and API routes

2. **Security regression checks**
   - CSRF token round trip
   - Anonymous write rejection contract
   - Participant and reviewer permission boundaries
   - Trusted-role handling
   - Session cookie flags
   - Production debug state
   - Safe error and security-header contracts

3. **Database and schema readiness**
   - MySQL PDO connection
   - Read-only query probe
   - Required table and expected-column verification

4. **Workflow consistency audit**
   - Stage 885 proof-review service readiness
   - Duplicate active receipt detection
   - Duplicate reward-event detection for the same receipt and rule
   - Orphan proof and review detection
   - Row-locking, task scoping, and finalized-review idempotency contracts
   - Preview-only award handoff boundary

5. **Same-origin live route probes**
   - GET requests only
   - TLS certificate verification enabled
   - Redirect following disabled
   - Current authenticated session cookie forwarded only to the same host

## Acceptance behavior

`ready_for_live_probe=true` means deployment, security, database/schema, and workflow consistency groups all scored 100/100.

`accepted=true` requires base readiness plus an explicit `?probe=1` run where every required live route returns a successful HTTP response.

## Automated validation

The main quality gate runs:

```bash
php ./tests/production-runtime-acceptance-contract-test.php
```

The test verifies route protection, GET-only API behavior, read-only SQL boundaries, safe probe configuration, anonymous write rejection, participant restrictions, trusted reviewer authorization, and CI runner integration.

## SQL status

No SQL required.
