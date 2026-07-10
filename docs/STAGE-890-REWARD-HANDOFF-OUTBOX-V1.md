# Stage 890 — Reward Handoff Outbox v1

## Purpose

Stage 890 replaces preview-only reward delivery state with a durable Training Lab outbox between an eligible `training_reward_events` row and an explicitly gated Microgifter reward adapter.

The outbox does not make production issuing automatic by default. It records delivery intent, resolves the recipient through the signed account-link system, owns idempotency and retries, and calls a direct adapter only when every production gate is open.

## SQL migration

Import once into the Training Lab database:

```text
database/stage890_reward_handoff_outbox_v1.sql
```

The migration creates:

```text
training_reward_handoffs
```

It does not alter Microgifter authentication, payment, wallet, claim, redemption, gift, or reward tables.

## Outbox lifecycle

```text
queued
blocked
processing
delivered
failed
cancelled
```

Each reward event can have only one outbox row. Each row also receives a stable SHA-256 idempotency key.

## Production gates

A delivery attempt requires all of the following:

1. `TL_REWARD_HANDOFF_PROCESSING_ENABLED=true`
2. `TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED=true`
3. A configured Microgifter developer API key
4. A direct adapter function such as `microgifter_issue_training_reward`
5. An active `training_account_links` row for the recipient
6. A non-cancelled reward event that has not already been issued or linked

If any requirement is missing, the outbox row remains `blocked`. Enabling a gate and running **Sync Eligible Rewards** refreshes blocked rows without creating duplicates.

## Recommended configuration

```text
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
TL_REWARD_HANDOFF_BATCH_SIZE=10
TL_REWARD_HANDOFF_MAX_ATTEMPTS=5
TL_REWARD_HANDOFF_RETRY_BASE_SECONDS=300
```

Keep processing disabled until the live adapter and developer key are verified. Retry delay uses capped exponential backoff.

## Routes

Human-readable operating page:

```text
/admin/reward-bridge.php
```

Machine-readable API:

```text
/api/training/reward-handoff-outbox.php
```

GET requires a trusted manager/admin session or developer key. POST requires the normal Training Lab write protections: authentication, role permission, CSRF, origin validation, rate limiting, and actor normalization.

## Supported operations

```text
enqueue_reward_handoff
sync_reward_handoff_outbox
process_reward_handoff
process_reward_handoff_batch
cancel_reward_handoff
```

Proof approvals through the Stage 885 protected action page and API automatically request an outbox sync after reward eligibility is evaluated.

## Delivery behavior

The direct adapter receives a server-built payload containing:

- stable idempotency key
- Training Lab reward and campaign references
- active account-link reference
- Microgifter user ID
- participant name and email from the trusted account link
- merchant and organization context
- reward type, value, currency, and linked template/product references

Passwords, password hashes, identity secrets, developer keys, and raw session data are never included.

On adapter confirmation, the outbox becomes `delivered` and the Training Lab reward event becomes `issued` or `linked`. External identifiers are recorded when supplied by the adapter.

On adapter failure, the outbox records the failure code, message, response, attempt count, and next retry time. Delivery attempts use row locks so concurrent workers cannot own the same handoff simultaneously.

Cancelling an outbox row cancels delivery only. It does not cancel the underlying Training Lab reward event.

## Deployment order

1. Merge and deploy Stage 890.
2. Preserve the private `/labs/config.php`.
3. Import `database/stage890_reward_handoff_outbox_v1.sql` into the Training Lab database.
4. Keep `TL_REWARD_HANDOFF_PROCESSING_ENABLED=false` during initial inspection.
5. Open `/admin/reward-bridge.php` and run **Sync Eligible Rewards**.
6. Confirm blocked/queued rows and recipient account links.
7. Configure and verify the direct Microgifter adapter.
8. Open production issuing and outbox processing only when ready.
9. Process one handoff manually before enabling batch processing.

## Validation

```bash
bash ./run-quality-gate.sh
```

The Stage 890 contract verifies:

- migration/table contract
- one handoff per reward event
- unique idempotency keys
- row-locked processing
- retry state and exponential backoff
- active account-link requirement
- explicit production gates
- direct adapter idempotency payload
- protected API and admin action routes
- credential and secret exclusion
- PHP 8.2 and PHP 8.3 quality-gate integration

## Safety boundaries

- No Training Lab wallet mutation
- No payment processing
- No claim/redeem mutation by Training Lab
- No developer key persistence or exposure
- No browser-supplied recipient identity
- No destructive synchronization
- Production issuing remains explicitly gated and disabled by default
