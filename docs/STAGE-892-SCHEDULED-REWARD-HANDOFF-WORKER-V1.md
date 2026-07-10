# Stage 892 — Scheduled Reward Handoff Worker v1

Stage 892 adds a cPanel/CLI-safe scheduled worker around the Stage 890 reward handoff outbox and Stage 891 lease-owned processor.

## Purpose

Stage 890 and Stage 891 provide durable delivery, retry, recovery, and operator controls. Before Stage 892, those operations could only be triggered from protected browser/API actions. Stage 892 adds a bounded command-line runner suitable for cPanel cron.

## Files

```text
bin/reward-handoff-worker.php
bin/.htaccess
includes/training-lab-stage892-scheduled-worker.php
api/training/reward-handoff-worker-status.php
tests/stage892-scheduled-worker-contract-test.php
```

The Reward Bridge page also displays the worker configuration and recent run status.

## Safety model

### CLI only

The worker exits immediately unless `PHP_SAPI === 'cli'`.

The `/bin/` directory also contains an Apache deny rule. There is no HTTP route that executes the worker. The only web route added by Stage 892 is a protected, read-only manager/admin status endpoint.

### Observe is the default

Running the script with no mode flag is the same as `--observe`:

```bash
php /path/to/training-lab/bin/reward-handoff-worker.php
php /path/to/training-lab/bin/reward-handoff-worker.php --observe
```

Observe mode:

- reads Stage 891 acceptance and queue status;
- does not synchronize the outbox;
- does not recover leases;
- does not process a handoff;
- does not call Microgifter;
- writes only a sanitized operational audit event to `training_events`.

### Recover mode

```bash
php /path/to/training-lab/bin/reward-handoff-worker.php --recover
```

Recover mode:

- requires `TL_REWARD_HANDOFF_WORKER_ENABLED=true`;
- recovers expired Stage 891 processing leases;
- never calls the Microgifter adapter;
- uses the existing Stage 891 row locks and terminal-failure rules.

### Process mode

```bash
php /path/to/training-lab/bin/reward-handoff-worker.php --process
```

Process mode requires all of the following:

1. the explicit `--process` CLI argument;
2. `TL_REWARD_HANDOFF_WORKER_ENABLED=true`;
3. `TL_REWARD_HANDOFF_PROCESSING_ENABLED=true`;
4. `TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED=true`;
5. a configured Microgifter developer API key;
6. a direct reward issue/claim adapter function;
7. the Stage 890 table;
8. an active account link for each reward recipient.

If any gate is closed, the run exits with a structured `skipped` result and does not call the adapter.

## Worker lock

Stage 892 uses a non-blocking exclusive `flock` lease. A second overlapping cron invocation exits without processing.

The lock file must be in a writable directory outside the deployed repository/public tree. The default is:

```text
/tmp/training-lab-stage892-reward-worker.lock
```

The exact temporary directory is resolved through `sys_get_temp_dir()`.

## Runtime bounds

The worker selects at most the configured batch size and checks the runtime deadline before starting each handoff.

The runtime budget does not interrupt an adapter call already in progress. It prevents the worker from starting another handoff after the deadline.

## Configuration

Keep the worker disabled during initial deployment:

```text
TL_REWARD_HANDOFF_WORKER_ENABLED=false
TL_REWARD_HANDOFF_WORKER_BATCH_SIZE=10
TL_REWARD_HANDOFF_WORKER_MAX_RUNTIME_SECONDS=45
TL_REWARD_HANDOFF_WORKER_ACTOR_USER_ID=1
TL_REWARD_HANDOFF_WORKER_LOCK_FILE=/tmp/training-lab-stage892-reward-worker.lock
```

Existing settings remain:

```text
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
TL_REWARD_HANDOFF_BATCH_SIZE=10
TL_REWARD_HANDOFF_MAX_ATTEMPTS=5
TL_REWARD_HANDOFF_RETRY_BASE_SECONDS=300
TL_REWARD_HANDOFF_LEASE_SECONDS=300
TL_REWARD_HANDOFF_RECOVERY_BATCH_SIZE=25
```

Do not store the Microgifter developer key, identity shared secret, or database password in cron command arguments.

## Recommended deployment sequence

1. Merge and deploy Stage 892.
2. Preserve the private `/labs/config.php`.
3. Leave both worker and production processing disabled.
4. Verify the protected status endpoint:

```text
/api/training/reward-handoff-worker-status.php
```

5. Run an observe command manually from the cPanel terminal:

```bash
php /home/CPANEL_USER/public_html/bin/reward-handoff-worker.php --observe
```

6. Confirm the JSON result reports:

```text
status=completed
mode=observe
safe_to_observe=true
```

7. Add an observe cron, for example every 15 minutes:

```cron
*/15 * * * * /usr/local/bin/php /home/CPANEL_USER/public_html/bin/reward-handoff-worker.php --observe >> /home/CPANEL_USER/logs/training-lab-worker.log 2>&1
```

8. After live shared-login and reward-adapter acceptance is complete, enable recovery first:

```text
TL_REWARD_HANDOFF_WORKER_ENABLED=true
```

```cron
*/10 * * * * /usr/local/bin/php /home/CPANEL_USER/public_html/bin/reward-handoff-worker.php --recover >> /home/CPANEL_USER/logs/training-lab-worker.log 2>&1
```

9. Enable `--process` only after Stage 891 reports production readiness and every Stage 890 gate is intentionally open.

## Exit codes

```text
0   completed or runtime-bounded partial completion
1   unexpected worker failure
2   safely skipped because a gate, schema, database, or overlap condition blocked execution
64  invalid command-line arguments
```

## Audit events

Stage 892 uses the existing `training_events` table:

```text
stage892_worker_started
stage892_worker_completed
stage892_worker_skipped
stage892_worker_failed
```

Audit metadata contains only run identifiers, mode, timings, counts, readiness values, and failure summaries. Credentials and shared secrets are not logged.

## Status endpoint

```text
GET /api/training/reward-handoff-worker-status.php
```

Access requires either:

- a trusted manager/admin session; or
- the existing valid developer-key boundary.

The endpoint never runs the worker.

## SQL

No SQL is required.

Stage 892 reuses:

```text
training_reward_handoffs
training_events
```

The imported Stage 890 migration remains sufficient.

## Validation

```bash
bash ./run-quality-gate.sh
```

The Stage 892 contract validates:

- default observe mode;
- explicit process mode;
- conflicting/invalid argument rejection;
- external writable lock path validation;
- overlap rejection;
- CLI-only enforcement;
- Apache web denial;
- worker enablement gates;
- Stage 890 adapter gates;
- bounded batch/runtime behavior;
- Stage 891 owned-worker processing;
- sanitized audit events;
- protected read-only status API;
- configuration examples;
- no new migration.

## Rollback

Disable the worker immediately:

```text
TL_REWARD_HANDOFF_WORKER_ENABLED=false
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
```

Remove any cPanel cron entry. No database rollback is needed because Stage 892 creates no table and changes no existing schema.
