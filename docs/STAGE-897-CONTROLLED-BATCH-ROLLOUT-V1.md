# Stage 897 — Controlled Batch Rollout v1

Stage 897 expands the proven Stage 896 one-reward pilot into a small, operator-selected rollout of two to five rewards. It does not add a new Microgifter endpoint, reward engine, queue, or scheduled worker.

## Purpose

Stage 896 proves one real reward can be issued and read back safely. Stage 897 verifies that the same permanent infrastructure behaves correctly across a small sequence before cron or normal automated processing is enabled.

## Permanent systems reused

- Stage 890 durable outbox and stable idempotency keys
- Stage 891 lease-owned processing and late-worker protection
- Stage 893 external delivery reconciliation
- Stage 894 signed read-only delivery lookup
- Stage 895 integration acceptance evidence
- Stage 896 signed issue client and canonical Microgifter issue endpoint
- Microgifter canonical Microgift and Action Center services

Every Stage 897 item is still executed through `tl_stage896_run_pilot()`. There is no batch issuance adapter.

## Routes

```text
/admin/reward-batch.php
/api/training/reward-batch.php
```

Both routes require a trusted manager, administrator, or developer key. POST actions use the existing CSRF, origin, authorization, request-size, and rate-limit controls.

## Preconditions

A batch can run only when:

1. Stage 897 is explicitly enabled.
2. Stage 896 is fully ready.
3. At least one Stage 896 delivery has been externally verified.
4. That verified-pilot evidence is still fresh.
5. No Stage 896 pilot is active.
6. No prior Stage 897 pause is awaiting acknowledgement.
7. The scheduled worker remains disabled.
8. Every selected reward passes a complete preflight before the first issue request.

## Batch boundaries

Default limits:

```text
TL_STAGE897_MAX_BATCH_SIZE=3
TL_STAGE897_MAX_TOTAL_VALUE_CENTS=7500
TL_STAGE897_VERIFIED_PILOT_MAX_AGE_SECONDS=604800
TL_STAGE897_MAX_RUNTIME_SECONDS=60
```

Hard limits:

- Minimum two rewards
- Maximum five rewards
- USD only
- Every item remains under the Stage 896 per-item value ceiling
- Configurable cumulative value ceiling
- Unique handoff IDs
- Exact linked Microgifter user ID re-entry for every item

The operator must type:

```text
ISSUE CONTROLLED BATCH
```

## Locking and concurrency

Stage 897 obtains its own non-blocking MySQL advisory lock and holds the Stage 896 advisory lock for the entire batch. Individual Stage 896 operations acquire and release a recursive reference to that lock. This prevents another manual pilot from starting between batch items.

The scheduled worker must remain disabled.

## Execution behavior

1. Validate every selected handoff, reward, account link, recipient confirmation, value, and currency.
2. Record a sanitized batch-start event.
3. Process one selected reward through Stage 896.
4. Require immediate signed read-back confirming external delivery.
5. Continue only when that item reaches `verified`.
6. Stop immediately on absence, uncertainty, adapter failure, reconciliation failure, exception, or runtime ceiling.
7. Record a completed or paused batch event.

A batch never skips a failed item and continues silently.

## Paused batches

A paused batch blocks the next batch until an operator reviews the result and types:

```text
ACKNOWLEDGE BATCH PAUSE
```

Acknowledgement is rejected while a Stage 896 pilot remains active or unresolved. The existing Stage 896 verification control must resolve uncertain delivery first.

## Evidence safety

Stored Stage 897 evidence includes only:

- Batch identifier
- Selected, processed, and verified counts
- Total value in cents and duration
- Sequence number
- Internal handoff/reward IDs
- Hashed recipient and handoff-reference fingerprints
- Delivery status and pause reason

It excludes shared secrets, signatures, nonces, raw recipient IDs, request payloads, raw adapter responses, claim credentials, and customer email addresses.

## Configuration

Deploy disabled:

```text
TL_STAGE897_CONTROLLED_BATCH_ENABLED=false
TL_STAGE897_MAX_BATCH_SIZE=3
TL_STAGE897_MAX_TOTAL_VALUE_CENTS=7500
TL_STAGE897_VERIFIED_PILOT_MAX_AGE_SECONDS=604800
TL_STAGE897_MAX_RUNTIME_SECONDS=60
```

Stage 896 and the signed issue/read clients must remain configured. Do not enable the scheduled worker during Stage 897.

## Rollout sequence

1. Deploy Stage 897 disabled.
2. Complete Stage 895 acceptance.
3. Complete and verify one Stage 896 reward.
4. Enable Stage 897.
5. Select two low-value rewards for the first batch.
6. Run the batch and confirm both are externally verified.
7. Increase to three, then at most five only after clean evidence.
8. Disable Stage 897 after rollout validation.

## Rollback

Immediately stop new batches:

```text
TL_STAGE897_CONTROLLED_BATCH_ENABLED=false
```

Keep Stage 894 read-back available to resolve an in-flight Stage 896 item. Keep the scheduled worker disabled.

## SQL

**No SQL required.**

Stage 897 reuses `training_reward_handoffs`, `training_reward_events`, and `training_events`.
