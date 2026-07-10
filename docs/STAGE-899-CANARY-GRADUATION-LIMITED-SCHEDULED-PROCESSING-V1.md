# Stage 899 — Canary Graduation & Limited Scheduled Processing v1

Stage 899 graduates the reward pipeline from Stage 898 one-item canaries to a separate, tightly bounded CLI scheduler. It does not activate the older Stage 892 worker and does not add a Microgifter endpoint or reward engine.

## Purpose

Stage 898 proves repeated single-item automated execution. Stage 899 verifies that the same signed, idempotent, read-back-confirmed path can safely process a very small scheduled batch before normal worker activation is considered.

## Permanent systems reused

- Stage 890 durable handoff outbox and stable idempotency keys
- Stage 891 lease ownership and recovery boundaries
- Stage 893 external delivery reconciliation
- Stage 894 signed read-only delivery lookup
- Stage 895 signed integration acceptance evidence
- Stage 896 signed issuance controller and immediate read-back
- Stage 897 controlled-batch evidence
- Stage 898 canary evidence and pause state

Every Stage 899 item calls `tl_stage896_run_pilot()`. There is no second issuance adapter.

## Routes and command

```text
/admin/reward-limited-scheduler.php
/api/training/reward-limited-scheduler.php
/bin/reward-limited-scheduler.php
```

The admin page and API provide status, health, escalation evidence, and suspension acknowledgement only. They cannot issue rewards.

```bash
php /path/to/training-lab/bin/reward-limited-scheduler.php --observe
php /path/to/training-lab/bin/reward-limited-scheduler.php --run
```

`--observe` is the default and is read-only. `--run` is required for processing.

## Graduation requirements

Stage 899 cannot run until:

1. At least three recent Stage 898 canaries completed with verified delivery.
2. The graduation window contains no paused or failed canary attempt.
3. The latest canary evidence is fresh.
4. Stage 898 execution is disabled to prevent two cron paths.
5. Stage 897 manual batch execution is disabled.
6. The original Stage 892 worker remains disabled.
7. Stage 896 and its signed issue/read-back path remain ready.
8. No Stage 896 pilot is active.
9. No Stage 898 pause or Stage 899 suspension is latched.
10. No reward handoff is quarantined for uncertain external delivery.

## Processing boundaries

Defaults:

```text
TL_STAGE899_LIMITED_SCHEDULER_ENABLED=false
TL_STAGE899_MAX_BATCH_SIZE=2
TL_STAGE899_MAX_ITEM_VALUE_CENTS=1000
TL_STAGE899_MAX_TOTAL_VALUE_CENTS=2000
TL_STAGE899_MIN_INTERVAL_SECONDS=1800
TL_STAGE899_MIN_VERIFIED_CANARIES=3
TL_STAGE899_CANARY_GRADUATION_WINDOW=5
TL_STAGE899_CANARY_EVIDENCE_MAX_AGE_SECONDS=86400
TL_STAGE899_HEALTH_WINDOW=10
TL_STAGE899_MIN_SUCCESS_RATE_PERCENT=100
TL_STAGE899_MAX_RUNTIME_SECONDS=90
TL_STAGE899_STALE_AFTER_SECONDS=10800
TL_STAGE899_ACTOR_USER_ID=1
TL_STAGE899_LOCK_FILE=/tmp/training-lab-stage899-limited-scheduler.lock
```

Hard boundaries:

- Maximum two rewards per run
- Maximum $10 per item by default
- Maximum $20 total per run by default
- USD only
- Untouched, due handoffs only
- Exact linked Microgifter recipient resolved internally
- Dedicated non-blocking lock outside the repository
- Minimum time between real attempts
- Immediate signed read-back for each item
- Stop on the first non-verified result

## Automatic suspension and escalation

Any of the following suspends the scheduler:

- Adapter or controller exception
- Non-verified delivery
- Unknown read-back state
- Runtime ceiling reached before the next selected item
- Unexpected scheduler exception

A suspension creates both:

```text
stage899_limited_processing_suspended
stage899_limited_processing_escalated
```

The evidence is sanitized and includes severity, run status, counts, value totals, queue counts, fingerprints, and duration. It excludes raw recipient IDs, secrets, signatures, nonces, payloads, adapter responses, claim credentials, and customer email addresses.

## Suspension clearance

The scheduler remains blocked until the cause is resolved and an operator types:

```text
ACKNOWLEDGE LIMITED PROCESSING SUSPENSION
```

Acknowledgement is rejected while:

- A Stage 896 pilot remains active
- A handoff remains quarantined
- A Stage 898 canary pause remains latched

## Monitoring

The protected monitor reports:

- Graduation count and freshness
- Readiness and health score
- Rolling completion rate
- Completed, idle, suspended, and skipped runs
- Verified and processed item totals
- Queue and quarantine counts
- Stale scheduler warning
- Dual-scheduler warnings
- Automatic suspension and escalation state

## Deployment sequence

1. Deploy Stage 899 disabled.
2. Complete Stage 895 acceptance.
3. Complete one Stage 896 pilot.
4. Complete a clean Stage 897 controlled batch.
5. Run at least three clean Stage 898 canaries.
6. Remove or disable the Stage 898 cron entry.
7. Set `TL_STAGE898_WORKER_CANARY_ENABLED=false`.
8. Keep Stage 897 and Stage 892 worker execution disabled.
9. Enable Stage 899.
10. Run `--observe` and confirm 100% readiness.
11. Run one manual Stage 899 cycle.
12. After clean evidence, schedule Stage 899 no faster than every 30 minutes.

## Rollback

Stop new runs immediately:

```text
TL_STAGE899_LIMITED_SCHEDULER_ENABLED=false
```

Remove the Stage 899 cron entry. Keep signed read-back available to resolve an in-flight Stage 896 item. Do not enable Stage 898 or Stage 892 until the active pilot and any quarantine are resolved.

## SQL

**No SQL required.**

Stage 899 reuses `training_reward_handoffs`, `training_reward_events`, and `training_events`.
