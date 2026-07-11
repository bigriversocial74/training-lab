# Production Integration Closeout v1

Section 20 is the final evidence and administrator-decision layer for Microgifter Training Lab v1. It does not create another workflow engine. It reads the durable records produced by the existing application, Resend, webhook reconciliation, reward outbox, and signed Microgifter endpoints.

## Required evidence

A 100% closeout report requires:

1. Current application code, private configuration, required migrations, and canonical product acceptance.
2. An active signed Microgifter account link with successful authentication and a consumed one-time nonce.
3. A published campaign that passes the completed Campaign Builder readiness checks.
4. Participant enrollment, submitted proof, approved review, verified task receipt, and verified `sequence_completed` receipt.
5. A graduated Section 18 email pilot, delivered administrator canary, at least one webhook-confirmed participant delivery, and no terminal delivery outcome.
6. A delivered Stage 890 reward handoff with a one-way external confirmation.
7. A ready Stage 894 signed lookup client and a passed 100% Stage 895 suite including known-reward and wrong-user isolation evidence.
8. Disabled unrestricted notification and reward workers, no handoff left in `processing`, and preserved payment/wallet/claim authority boundaries.

## SQL required

Back up the Training Lab database, then import after all existing migrations:

```text
database/production_integration_closeout_v1.sql
```

It creates:

```text
training_integration_closeout_runs
training_integration_closeout_checks
training_integration_closeout_events
```

The migration is additive and does not alter Microgifter users, passwords, payments, wallets, gifts, claims, redemptions, or rewards.

## Disabled-first configuration

Keep both controls disabled during deployment:

```php
'production_integration_closeout_enabled' => false,
'production_integration_closeout_approval_enabled' => false,
```

Preferred environment variables:

```bash
TL_PRODUCTION_INTEGRATION_CLOSEOUT_ENABLED=false
TL_PRODUCTION_INTEGRATION_CLOSEOUT_APPROVAL_ENABLED=false
```

These controls affect only evidence recording and final administrator approval. They do not enable email, webhook, reward, worker, reconciliation, payment, wallet, claim, or redemption behavior.

## Deployment order

1. Back up files and the Training Lab database.
2. Deploy the current verified release while preserving `/labs/config.php`.
3. Confirm all earlier migrations are imported.
4. Import `database/production_integration_closeout_v1.sql`.
5. Run:

```bash
bash ./run-full-syntax-check.sh
bash ./run-quality-gate.sh
php ./bin/product-acceptance.php
php ./bin/integration-closeout.php --json
```

6. Complete the real cross-site and provider sequence:

```text
Microgifter signed login
→ published Training Lab campaign
→ enrollment
→ task
→ proof
→ approved review
→ campaign completion
→ Resend participant delivery
→ signed webhook reconciliation
→ controlled Microgift issue
→ signed lookup confirmation
```

7. Enable recording only:

```bash
TL_PRODUCTION_INTEGRATION_CLOSEOUT_ENABLED=true
TL_PRODUCTION_INTEGRATION_CLOSEOUT_APPROVAL_ENABLED=false
```

8. Open `/admin/integration-closeout.php`, evaluate the target campaign, and record a snapshot.
9. Resolve every failed check. Evidence snapshots are idempotent and immutable; record a new snapshot after evidence changes.
10. Enable the separate approval gate only when the recorded report is 100%:

```bash
TL_PRODUCTION_INTEGRATION_CLOSEOUT_APPROVAL_ENABLED=true
```

11. Approve the unchanged recorded report. Approval fails if the current evidence hash no longer matches.
12. Disable both closeout gates after the decision if no additional snapshots are required.

## Operations

Human dashboard:

```text
/admin/integration-closeout.php
```

Read-only JSON:

```text
/api/training/integration-closeout.php
```

Read-only CLI:

```bash
php ./bin/integration-closeout.php --campaign=CAMPAIGN_REF --json
```

The CLI returns exit code `0` only when every mandatory check passes, `2` when evidence is blocked, and `1` for an execution failure.

## Privacy and authority boundaries

- No email address is displayed or copied into closeout tables.
- Provider message IDs, webhook bodies, API secrets, signatures, nonces, and full external references are excluded.
- Only short one-way fingerprints appear in reports.
- Recording and decisions write only Section 20 tables.
- No closeout action sends email, executes a worker, enables a gate, issues a reward, changes a wallet, processes a payment, claims, redeems, or writes to Microgifter.
- Approval is evidence of a completed production path; it is not permission for unrestricted automation.

## Emergency stop

If live verification exposes a problem:

```bash
TL_PRODUCTION_INTEGRATION_CLOSEOUT_ENABLED=false
TL_PRODUCTION_INTEGRATION_CLOSEOUT_APPROVAL_ENABLED=false
TL_NOTIFICATION_DELIVERY_ENABLED=false
TL_NOTIFICATION_WORKER_ENABLED=false
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
TL_REWARD_HANDOFF_WORKER_ENABLED=false
```

Pause the affected campaign and pilot, retain all evidence records, and investigate before recording a new snapshot.

## Rollback

1. Disable both Section 20 gates.
2. Restore the previous verified application package while preserving `/labs/config.php`.
3. Leave the three closeout tables in place for audit history.
4. Do not delete account links, proofs, reviews, receipts, email events, reward handoffs, or Microgifter confirmation evidence.
5. Destructive schema rollback is neither required nor recommended.
