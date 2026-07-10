# Stage 898 — Scheduled Worker Canary & Monitoring v1

Stage 898 introduces the first scheduled production canary without enabling the normal Stage 892 reward worker. A CLI command automatically selects at most one untouched, low-value reward and runs it through the proven Stage 896 single-item controller.

## Purpose

Stages 895–897 prove signed connectivity, one manual reward, and a controlled operator batch. Stage 898 proves that the same permanent infrastructure can be triggered by cron while retaining a one-item ceiling, immediate signed read-back, health evidence, and a hard automatic pause.

## No new Microgifter endpoint

Stage 898 reuses:

- Stage 890 durable outbox and idempotency keys
- Stage 891 lease-owned processing
- Stage 893 reconciliation
- Stage 894 signed delivery lookup
- Stage 895 acceptance evidence
- Stage 896 signed pilot issue client and canonical Microgifter issue endpoint
- Stage 897 clean controlled-batch evidence

The normal Stage 892 worker stays disabled. No Microgifter repository change is required.

## Routes and commands

Protected monitoring surfaces:

```text
/admin/reward-worker-canary.php
/api/training/reward-worker-canary.php
```

CLI only:

```text
php /path/to/training-lab/bin/reward-worker-canary.php --observe
php /path/to/training-lab/bin/reward-worker-canary.php --run
```

The web routes cannot execute the canary. They provide status and allow a trusted operator to acknowledge a pause after resolving any active Stage 896 pilot.

## Preconditions

A canary run requires:

1. Stage 898 explicitly enabled.
2. Stage 896 fully ready.
3. The latest Stage 897 rollout state is a clean completed batch.
4. Every Stage 897 item was processed and externally verified.
5. That evidence remains fresh.
6. No Stage 896 pilot is active.
7. No Stage 898 pause is latched.
8. The dedicated lock path is writable and outside the deployed repository.
9. The minimum interval since the previous real canary attempt has elapsed.
10. The normal Stage 892 worker remains disabled.

## Candidate selection

The canary selects at most one reward that is:

- queued or retryable failed
- due now
- below the configured attempt limit
- not quarantined for external-delivery reconciliation
- not previously used by Stage 896
- not already issued, linked, or cancelled
- USD
- at or below the Stage 898 value ceiling
- linked to an active Microgifter recipient

The default value ceiling is $10.00.

## Execution

1. Acquire the Stage 898 non-blocking file lock.
2. Recheck all mutable readiness gates.
3. Snapshot queue health.
4. Select one eligible candidate.
5. Invoke `tl_stage896_run_pilot()` with the server-verified linked Microgifter user ID.
6. Require immediate Stage 894 read-back confirming external delivery.
7. Record sanitized success evidence or latch a pause.

No candidate is a healthy idle run.

## Automatic pause

Any exception or non-verified delivery records:

```text
stage898_worker_canary_paused
```

All later canary runs are blocked until:

1. any active Stage 896 pilot is resolved through the existing pilot controls; and
2. an operator types:

```text
ACKNOWLEDGE CANARY PAUSE
```

## Monitoring

The monitor reports:

- readiness score
- health state
- last run and age
- verified, idle, paused, failed, and skipped counts
- attempted-run success rate
- due, failed, processing, and quarantined queue counts
- stale-canary alert
- pause-latch alert
- normal-worker-enabled alert
- active-pilot alert

Cron can use the CLI exit code:

- `0` — verified or healthy idle
- `1` — exception and automatic pause
- `2` — skipped because a safety gate is closed or another run overlaps
- `3` — delivery was not externally verified and the pause latch is active
- `64` — invalid CLI arguments

## Configuration

Deploy disabled:

```text
TL_STAGE898_WORKER_CANARY_ENABLED=false
TL_STAGE898_MAX_VALUE_CENTS=1000
TL_STAGE898_MIN_INTERVAL_SECONDS=900
TL_STAGE898_STAGE897_EVIDENCE_MAX_AGE_SECONDS=604800
TL_STAGE898_STALE_AFTER_SECONDS=7200
TL_STAGE898_HEALTH_WINDOW=20
TL_STAGE898_ACTOR_USER_ID=1
TL_STAGE898_LOCK_FILE=/tmp/training-lab-stage898-worker-canary.lock
```

Keep the normal worker disabled:

```text
TL_REWARD_HANDOFF_WORKER_ENABLED=false
```

Stage 896 and its signed lookup/issue configuration must remain available.

## Rollout sequence

1. Deploy Stage 898 disabled.
2. Complete Stage 895 acceptance.
3. Complete one verified Stage 896 pilot.
4. Complete one clean Stage 897 batch.
5. Run the Stage 898 observe command.
6. Enable Stage 898.
7. Run one canary manually from CLI.
8. Confirm verified delivery and healthy monitoring.
9. Add a conservative cron interval, initially 15 minutes or slower.
10. Review several clean canary runs before normal worker activation is considered.

## Rollback

Disable immediately:

```text
TL_STAGE898_WORKER_CANARY_ENABLED=false
```

Do not enable the normal Stage 892 worker. Keep Stage 894 read-back and Stage 896 verification available to resolve an uncertain in-flight reward.

## Evidence safety

Stored evidence includes only operational status, internal handoff/reward IDs, value, duration, queue counts, and hashed recipient/reference fingerprints. It excludes secrets, signatures, nonces, raw recipient IDs, payloads, adapter responses, claim credentials, and email addresses.

## SQL

**No SQL required.**

Stage 898 reuses `training_reward_handoffs`, `training_reward_events`, and `training_events`.
