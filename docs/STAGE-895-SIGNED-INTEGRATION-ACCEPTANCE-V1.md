# Stage 895 — Signed Integration Acceptance & Controlled Rollout v1

Stage 895 verifies the deployed Stage 894 Training Lab-to-Microgifter lookup connection before reconciliation or reward processing is enabled.

## Purpose

The Stage 894 endpoint and client are merged, but production readiness requires evidence that the deployed servers share the same secret, enforce the same HMAC contract, reject replay and tampering, isolate rewards by user identity, and return truthful found/not-found results.

Stage 895 performs only signed, read-only lookup requests. It does not issue, claim, redeem, cancel, refund, replace, transfer, or mutate rewards.

## Safety gates

A live suite is refused unless all of these are true:

```text
TL_STAGE895_LIVE_ACCEPTANCE_ENABLED=true
TL_MICROGIFTER_REWARD_LOOKUP_ENABLED=true
TL_REWARD_RECONCILIATION_ENABLED=false
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
TL_REWARD_HANDOFF_WORKER_ENABLED=false
```

The Microgifter endpoint must also be enabled with the matching private shared secret:

```text
MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED=true
MG_TRAINING_LAB_REWARD_LOOKUP_SECRET=<same private 32+ character secret>
```

Never commit or paste the secret into an acceptance request.

## Acceptance probes

The suite runs seven required checks:

1. Local HTTPS, allowlist, secret, cURL, and production-gate readiness
2. Valid signed request using a synthetic missing reward reference
3. Tampered-signature rejection
4. Expired-timestamp rejection
5. Identical nonce replay rejection
6. Known identity-bound reward confirmation
7. Wrong-user isolation for that known reward

The known reward probe accepts either:

- the canonical Microgift idempotency key, or
- the Microgift public ID / supported external reference

The wrong-user ID must belong to an unrelated Microgifter account.

## Operator routes

```text
/admin/integration-acceptance.php
/api/training/integration-acceptance.php
```

The Reward Bridge includes a Stage 895 link to the protected acceptance page.

GET access requires a trusted manager/admin session or Training Lab developer key. POST actions use the central authorization, origin, rate-limit, and CSRF controls.

## Evidence

Each completed suite records a `stage895_signed_integration_acceptance` event through the existing Training Lab event logger.

Stored evidence is intentionally limited to:

- suite ID
- overall status and score
- ready/not-ready result
- hashed user and reward-reference fingerprints
- probe identifiers and pass/fail/skip status
- HTTP status
- sanitized remote error code
- request ID
- duration

The event excludes:

- shared secret
- HMAC signature
- nonce
- raw request body
- raw response body
- raw user ID
- raw idempotency key
- raw external reference

## Readiness result

`ready_for_reconciliation=true` requires all seven probes to pass while every production-processing gate remains closed.

A suite without a known reward and unrelated user ID may validate transport security, but its status remains `incomplete` and it cannot approve reconciliation.

## Rollout after a passing suite

1. Keep handoff processing and the scheduled worker disabled.
2. Enable only `TL_REWARD_RECONCILIATION_ENABLED=true`.
3. Reconcile one quarantined delivery manually.
4. Confirm the local handoff and reward event match the canonical Microgift.
5. Disable reconciliation immediately if the result is inconsistent.
6. Build and run the Stage 896 limited issuance pilot before enabling normal processing.

## Rollback

Disable the Training Lab client and acceptance suite:

```text
TL_STAGE895_LIVE_ACCEPTANCE_ENABLED=false
TL_MICROGIFTER_REWARD_LOOKUP_ENABLED=false
TL_REWARD_RECONCILIATION_ENABLED=false
TL_REWARD_HANDOFF_PROCESSING_ENABLED=false
TL_REWARD_HANDOFF_WORKER_ENABLED=false
```

Disable the Microgifter endpoint when the signed integration should be fully offline:

```text
MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED=false
```

No reward or outbox data is deleted by rollback.

## SQL

**No SQL required.**

Stage 895 reuses the existing Training Lab event logger and Microgifter Stage 894 nonce/idempotency storage.
