# End-to-End Acceptance + Deployment v1

## Purpose

This section provides one read-only acceptance report for the complete Training Lab product surface currently present in the repository. It verifies required routes, services, database tables, quality contracts, and protected Microgifter boundaries.

## Deployment order

1. Back up the current application files and Training Lab database.
2. Preserve the active `config.php` and any private environment or signing values.
3. Confirm the existing Training Lab schema and Stage 890 `training_reward_handoffs` migration are present.
4. Deploy the application files from the accepted branch.
5. Run `bash ./run-full-syntax-check.sh`.
6. Run `bash ./run-quality-gate.sh`.
7. Run `php ./bin/product-acceptance.php` from the command line.
8. Sign in as participant, reviewer, manager, and administrator accounts and smoke-test only the routes available to each role.
9. Keep reward workers, pilots, canaries, and limited scheduling at their existing configuration. Do not enable production delivery as part of this product deployment.

## SQL

Sections 10–13 require no new SQL.

The deployment still requires the previously established Training Lab schema and existing Stage 890 handoff table. The acceptance report fails closed when a required table is missing.

## Acceptance scope

The report checks:

- participant home, campaign, task, progress, onboarding, and accessibility routes;
- merchant management, onboarding, reward-rule, analytics, and fulfillment routes;
- administrator-only advanced reward operations;
- core product services and role-aware access layer;
- required Training Lab tables and the Stage 890 outbox table;
- section contract tests and quality runners;
- account, security, signed lookup, and limited scheduler boundaries.

## Rollback

1. Disable web traffic or enter maintenance mode if the deployment is unhealthy.
2. Restore the previous application file package.
3. Restore the preserved configuration without changing secrets.
4. Do not delete Training Lab rows created before rollback.
5. Keep reward delivery gates disabled or at their prior state.
6. Re-run syntax, quality, and product acceptance against the restored package.

Because Sections 10–13 add no SQL, rollback does not require a destructive schema reversal.

## Safety boundary

Acceptance is read-only. It does not import SQL, alter configuration, create accounts or roles, issue rewards, claim gifts, change wallet balances, run workers, reconcile delivery, or contact Microgifter.
