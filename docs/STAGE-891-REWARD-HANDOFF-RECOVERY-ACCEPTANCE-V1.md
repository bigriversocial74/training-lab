# Stage 891 — Reward Handoff Recovery + Operational Acceptance v1

## Purpose

Stage 891 hardens the Stage 890 reward handoff outbox before real Microgifter issuing is enabled.

It addresses three operational risks:

1. A PHP worker or request can stop after claiming a handoff and leave the row permanently stuck in `processing`.
2. A delivery can exhaust its automatic attempts and require an explicit operator decision before another retry cycle.
3. A late worker can return after its lease was recovered and attempt to apply an outdated adapter result.

Stage 891 adds lease recovery, terminal-failure review, operator requeue, owned-worker finalization, and a read-only acceptance summary.

## Database status

No new Stage 891 migration is required.

Stage 891 uses the Stage 890 table that has already been imported into the Training Lab database:

```text
training_reward_handoffs
```

Existing migration:

```text
database/stage890_reward_handoff_outbox_v1.sql
```

Target database:

```text
ywzyeite_microlabs
```

Do not import the Stage 890 migration into the Microgifter database.

## Configuration

Keep production processing disabled while live browser and adapter acceptance remains deferred:

```text
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
```

Stage 891 adds these optional Training Lab settings:

```text
TL_REWARD_HANDOFF_LEASE_SECONDS=300
TL_REWARD_HANDOFF_RECOVERY_BATCH_SIZE=25
```

Defaults:

- Worker lease: 300 seconds
- Recovery batch: 25 rows
- Lease minimum: 60 seconds
- Lease maximum: 3,600 seconds
- Recovery batch maximum: 100 rows

The existing Stage 890 settings remain active:

```text
TL_REWARD_HANDOFF_BATCH_SIZE=10
TL_REWARD_HANDOFF_MAX_ATTEMPTS=5
TL_REWARD_HANDOFF_RETRY_BASE_SECONDS=300
```

## Worker lease recovery

A handoff is considered abandoned when:

- its status is `processing`;
- `locked_at` is present; and
- the lock is older than `TL_REWARD_HANDOFF_LEASE_SECONDS`.

Recovery performs a row-locked update.

If attempts remain, the row becomes retryable:

```text
handoff_status = failed
failure_code = worker_lease_expired_recovered
next_attempt_at = current UTC time
locked_at = null
locked_by = null
```

If the row has reached the maximum attempt count, it becomes a terminal operator-review item:

```text
handoff_status = failed
failure_code = worker_lease_expired_terminal
next_attempt_at = null
locked_at = null
locked_by = null
```

Recovery never calls a Microgifter adapter and never mutates a Microgifter reward, claim, wallet, or payment record.

## Owned-worker finalization

Every processing attempt receives a unique worker token.

The adapter call occurs outside the database transaction, but the result can be applied only when the worker still owns the lease:

```text
handoff_status = processing
locked_by = <same worker token>
```

Delivered and failed finalization updates include both predicates.

If the lease was recovered, cancelled, or transferred while the adapter call was running, the returning worker:

- does not update the handoff;
- does not update the Training Lab reward event;
- records a `stage891_worker_lease_lost` audit event; and
- returns `adapter_result_unapplied=true`.

The idempotency key is still sent to the Microgifter adapter. The adapter must honor that key before production delivery is enabled.

## Terminal failure queue

The Reward Bridge includes a terminal-failure panel for handoffs that:

- have status `failed`;
- reached the configured maximum attempts; and
- have no scheduled next attempt.

An authorized operator can requeue a reviewed failure.

Requeue behavior:

- row-locked handoff selection;
- rejects delivered, cancelled, and active processing rows;
- rejects rewards already issued, linked, or cancelled;
- resets `attempt_count` to `0`;
- sets the handoff and reward back to `queued`;
- preserves an operator recovery history; and
- writes a `stage891_handoff_requeued` audit event.

Requeue does not call Microgifter. A later explicitly gated processing action performs delivery.

## Operational acceptance

Stage 891 reports two different readiness states.

### Safe to observe

`safe_to_observe=true` requires:

- Stage 890 schema present;
- consistency queries successful;
- no stale processing leases;
- no orphan handoffs;
- no delivered-handoff/reward-state mismatch;
- no duplicate idempotency keys;
- no duplicate delivered external references; and
- valid lease and recovery policies.

This state can be achieved while production processing remains disabled.

### Ready for production processing

`ready_for_production_processing=true` additionally requires:

- every Stage 890 production gate open;
- no terminal failures awaiting operator review; and
- the outbox already safe to observe.

The production gates remain:

```text
TL_REWARD_HANDOFF_PROCESSING_ENABLED=true
TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED=true
Microgifter developer API key present
Direct reward-issue adapter function present
Active Training Lab account link for the recipient
Reward is not already issued, linked, or cancelled
```

Do not enable these gates solely because Stage 891 code is deployed. Complete the deferred account-integration and live adapter acceptance first.

## Routes

Manager/admin read and operation API:

```text
GET  /api/training/reward-handoff-operations.php
POST /api/training/reward-handoff-operations.php
```

Stage 890 API also routes its processing actions through the Stage 891 owned-worker processor:

```text
GET  /api/training/reward-handoff-outbox.php
POST /api/training/reward-handoff-outbox.php
```

Admin interface:

```text
/admin/reward-bridge.php
```

## Protected actions

```text
stage891_run_handoff_acceptance
stage891_recover_stale_handoffs
stage891_requeue_handoff
stage891_process_resilient_batch
```

Legacy Stage 890 processing action names remain compatible but are routed to Stage 891 ownership enforcement:

```text
process_reward_handoff
process_reward_handoff_batch
```

## Deployment order

1. Merge Stage 891 into `main`.
2. Deploy the latest Training Lab `main` while preserving the private `/labs/config.php`.
3. Confirm the previously imported `training_reward_handoffs` table remains present.
4. Add the optional lease and recovery environment settings.
5. Keep `TL_REWARD_HANDOFF_PROCESSING_ENABLED=false`.
6. Open `/admin/reward-bridge.php` with a trusted manager/admin account.
7. Run **Recover Stale Workers**.
8. Run **Run Acceptance**.
9. Review any terminal failures and blocked requirements.
10. Leave production delivery disabled until the deferred live account and adapter tests are complete.

## Validation

Run:

```bash
bash ./run-quality-gate.sh
```

The Stage 891 contract verifies:

- lease-expiration calculations;
- terminal-failure classification;
- stale-row recovery with row locking;
- no adapter calls during recovery or requeue;
- operator audit history;
- owned-worker finalization predicates;
- late-worker result rejection;
- protected manager/admin APIs;
- legacy processing routes using the owned processor;
- terminal failure UI controls;
- configuration examples; and
- no Stage 891 SQL requirement.

CI runs the complete repository quality gate on PHP 8.2 and PHP 8.3.

## Safety boundaries

Stage 891 does not add:

- a new database table;
- payment processing;
- Training Lab wallet mutation;
- claim or redemption mutation by Training Lab;
- browser-supplied recipient identity;
- secret storage in the outbox;
- automatic production issuing; or
- destructive synchronization back to Microgifter.
