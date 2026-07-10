# Stage 893 — External Delivery Reconciliation v1

Stage 893 closes the duplicate-issue risk that exists when a Microgifter adapter reports success after the original Training Lab worker has lost ownership of its processing lease.

## Problem

Stage 891 correctly refuses to apply a late adapter result after worker ownership changes. That protects local transaction integrity, but the external Microgifter mutation may already have succeeded.

Without reconciliation, the Training Lab handoff could later be retried and create a second reward unless the external adapter independently enforces the idempotency key.

## Stage 893 behavior

Stage 893 adds four controls:

1. **Lost-success quarantine**
   - A lease-lost result with an unapplied adapter response is moved to `blocked` when the handoff is no longer owned by a replacement worker.
   - The handoff receives failure code:

     ```text
     external_delivery_confirmation_required
     ```

   - `next_attempt_at` is cleared.
   - The normal due-handoff query explicitly excludes the reconciliation failure code.

2. **Read-only external lookup**
   - A Microgifter lookup adapter receives the handoff idempotency key, external reference, account reference and Training Lab reward identifiers.
   - Stage 893 does not pass passwords, sessions, developer keys or shared identity secrets.
   - The lookup payload declares `read_only=true`.

3. **Confirmed local repair**
   - If the lookup confirms delivery, Stage 893 updates the existing handoff to `delivered` and the Training Lab reward event to `issued` or `linked`.
   - It does not create a second Microgifter reward.
   - A delivered outbox row with a mismatched local reward state can be repaired without an external write.

4. **Unconfirmed delivery remains blocked**
   - Missing, pending, failed or unavailable lookups never release the handoff for retry.
   - An operator must install/fix the read adapter or investigate Microgifter before the handoff can be finalized.

## Configuration

Keep reconciliation disabled during the first deployment:

```text
TL_REWARD_RECONCILIATION_ENABLED=false
TL_REWARD_RECONCILIATION_BATCH_SIZE=25
TL_REWARD_RECONCILIATION_MIN_AGE_SECONDS=300
```

Equivalent private `/labs/config.php` values:

```php
'reward_delivery_reconciliation_enabled' => false,
'reward_delivery_reconciliation_batch_size' => 25,
'reward_delivery_reconciliation_min_age_seconds' => 300,
```

The minimum-age setting applies to automatic batch reconciliation. A protected operator-triggered single reconciliation can run immediately.

## Read adapter contract

Install one of these callable functions in the Training Lab runtime:

```text
microgifter_training_reward_lookup
microgifter_lookup_training_reward
microgifter_find_reward_by_idempotency_key
microgifter_reward_delivery_status
```

The function receives one array:

```php
[
    'contract' => 'training_lab_reward_reconciliation_v1',
    'source' => 'training_lab',
    'idempotency_key' => '...',
    'external_reference' => '...',
    'training_handoff_id' => 123,
    'training_handoff_public_id' => '...',
    'training_reward_event_id' => 456,
    'training_reward_public_id' => '...',
    'training_user_id' => 789,
    'microgifter_user_id' => '...',
    'read_only' => true,
]
```

Accepted result fields include:

```php
[
    'found' => true,
    'status' => 'issued', // issued, linked, delivered, completed
    'external_reference' => 'MG-REWARD-123',
    'gift_id' => 1001,
    'microgift_instance_id' => null,
    'digital_entitlement_id' => null,
    'wallet_event_id' => null,
]
```

Pending, missing or failed records should return a truthful state such as:

```php
['found' => false, 'status' => 'not_found']
```

or:

```php
['found' => true, 'status' => 'processing']
```

The adapter must perform a read-only lookup. It must not create, issue, claim, redeem or cancel a reward.

## Protected routes

Human-readable operations panel:

```text
/admin/reward-bridge.php
```

Machine-readable status and actions:

```text
/api/training/reward-delivery-reconciliation.php
```

GET requires a trusted manager/admin session or developer key.

POST actions:

```text
stage893_reconcile_delivery
stage893_reconcile_delivery_batch
```

POST requires authentication, manager/admin operations permission, origin validation and CSRF protection.

## Worker integration

The existing CLI command remains:

```bash
php /path/to/training-lab/bin/reward-handoff-worker.php --observe
php /path/to/training-lab/bin/reward-handoff-worker.php --recover
php /path/to/training-lab/bin/reward-handoff-worker.php --process
```

Stage 893 changes process mode behavior:

- `--process` is rejected when reconciliation is disabled.
- A reconciliation batch runs before normal handoff delivery.
- The normal queue excludes reconciliation-quarantined rows.
- Any lost-success outcome returned by the Stage 892 worker is quarantined after the run.

Observe and recover modes retain their Stage 892 safety boundaries.

## Deployment order

1. Merge and deploy Stage 893.
2. Preserve the private `/labs/config.php`.
3. Keep these disabled:

   ```text
   TL_REWARD_RECONCILIATION_ENABLED=false
   TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
   TL_REWARD_HANDOFF_WORKER_ENABLED=false
   ```

4. Install and validate a read-only Microgifter lookup adapter.
5. Open `/admin/reward-bridge.php` and confirm the Stage 893 panel detects the adapter.
6. Enable reconciliation only:

   ```text
   TL_REWARD_RECONCILIATION_ENABLED=true
   ```

7. Run the protected reconciliation batch while production issuing remains disabled.
8. Confirm quarantined and mismatch counts reach zero or are understood.
9. Complete the deferred live Stage 888 account test and reward adapter acceptance.
10. Enable worker and production processing only after all gates pass.

## Rollback

Disable Stage 893 immediately:

```text
TL_REWARD_RECONCILIATION_ENABLED=false
```

Keep production reward processing disabled while investigating:

```text
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
TL_REWARD_HANDOFF_WORKER_ENABLED=false
```

Disabling Stage 893 does not delete data and does not automatically release quarantined handoffs.

## SQL

No SQL is required.

Stage 893 reuses:

```text
training_reward_handoffs
training_reward_events
```

The Stage 890 migration already imported into `ywzyeite_microlabs` remains sufficient.

## Safety boundaries

- No issue/claim adapter is called by reconciliation.
- No payment processing.
- No wallet balance mutation by Training Lab.
- No claim/redeem mutation.
- No password or authentication-secret transfer.
- No automatic retry of uncertain external delivery.
- No new database migration.
