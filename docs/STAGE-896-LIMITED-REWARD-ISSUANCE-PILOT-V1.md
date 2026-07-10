# Stage 896 — Limited Reward Issuance Pilot v1

Stage 896 is a temporary production-control layer over the permanent Stage 890–895 reward infrastructure. It issues one selected reward, verifies that delivery through the signed Stage 894 read adapter, and blocks all additional pilots until terminal evidence exists.

## Permanent systems reused

- Stage 890 durable outbox and idempotency key
- Stage 891 lease-owned processing and late-worker protection
- Stage 893 external delivery quarantine and reconciliation
- Stage 894 signed read-only Microgifter lookup
- Stage 895 signed integration acceptance evidence
- Microgifter canonical `mg_microgift_issue()` engine
- Microgifter Action Center projections

Stage 896 does not create another reward engine or another database queue.

## Training Lab routes

```text
/admin/reward-pilot.php
/api/training/reward-pilot.php
```

Both routes require a trusted manager, administrator, or developer key. POST operations use the existing CSRF, origin, authorization, request-size, and rate-limit controls.

## Pilot rules

A pilot can start only when:

1. Stage 896 is explicitly enabled.
2. The latest Stage 895 acceptance event passed all checks with a score of 100.
3. That acceptance event is still within the configured age limit.
4. The Stage 894 signed lookup client is ready.
5. External delivery reconciliation is enabled.
6. Manual handoff processing and production issuing are enabled.
7. The signed Stage 896 issue client is ready.
8. The scheduled worker is disabled.
9. No other pilot is active.
10. The selected reward is below the value ceiling, uses USD, has an active account link, and references a published Microgift template.

The operator must re-enter the exact linked Microgifter user ID and type:

```text
ISSUE ONE PILOT
```

## Signed issue client

Training Lab calls:

```text
https://microgifter.com/api/integrations/training-lab-reward-pilot-issue.php
```

The client registers the established adapter hook:

```php
microgifter_training_issue_reward(array $payload): array
```

only when the dedicated endpoint, host allowlist, HMAC secret, and cURL transport are ready. Runtime guards additionally require the single active handoff to be in `processing` with matching Stage 896 pilot metadata.

## Training Lab configuration

Deploy disabled:

```text
TL_STAGE896_LIMITED_PILOT_ENABLED=false
TL_STAGE896_MAX_VALUE_CENTS=2500
TL_STAGE896_ACCEPTANCE_MAX_AGE_SECONDS=86400

TL_MICROGIFTER_PILOT_ISSUE_ENABLED=false
TL_MICROGIFTER_PILOT_ISSUE_URL=https://microgifter.com/api/integrations/training-lab-reward-pilot-issue.php
TL_MICROGIFTER_PILOT_ISSUE_SECRET=<dedicated minimum 32-character random secret>
TL_MICROGIFTER_PILOT_ISSUE_ALLOWED_HOSTS=microgifter.com,www.microgifter.com
TL_MICROGIFTER_PILOT_ISSUE_TIMEOUT_SECONDS=10
TL_MICROGIFTER_PILOT_ISSUE_CONNECT_TIMEOUT_SECONDS=3
TL_MICROGIFTER_PILOT_ISSUE_MAX_RESPONSE_BYTES=131072
```

Use a dedicated issue secret. Do not reuse the Stage 894 read-only lookup secret.

## Required pilot rollout state

```text
TL_MICROGIFTER_REWARD_LOOKUP_ENABLED=true
TL_REWARD_RECONCILIATION_ENABLED=true
TL_REWARD_HANDOFF_PROCESSING_ENABLED=true
TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED=true
TL_REWARD_HANDOFF_WORKER_ENABLED=false
TL_MICROGIFTER_PILOT_ISSUE_ENABLED=true
TL_STAGE896_LIMITED_PILOT_ENABLED=true
```

On Microgifter:

```text
MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED=true
MG_TRAINING_LAB_PILOT_ISSUE_ENABLED=true
```

The read-only lookup and issue endpoints use separate secrets.

## Execution sequence

1. Complete Stage 895 with a 100% passing suite.
2. Confirm the candidate is low value and tied to the intended active account link.
3. Open `/admin/reward-pilot.php`.
4. Select one handoff.
5. Re-enter the linked Microgifter user ID.
6. Type `ISSUE ONE PILOT`.
7. Submit the single pilot.
8. Stage 896 reserves the handoff under a global MySQL advisory lock.
9. The existing lease-owned processor invokes the signed pilot issue client.
10. Microgifter resolves the merchant from the signed workspace context, validates the published template, and calls the canonical idempotent issue engine.
11. Microgifter projects the reward into Action Center.
12. Training Lab immediately performs signed read-back by idempotency key.
13. The pilot becomes `verified`, `closed_absent`, or remains blocked for verification.

## Terminal evidence

Only these states release the next pilot:

- `verified`: Microgifter confirms delivery.
- `closed_absent`: processing did not succeed and Microgifter confirms no delivery exists.
- `cancelled_before_processing`: reserved pilot was explicitly cancelled before any adapter call.

Any uncertain, failed, or pending verification state blocks the next pilot.

## Evidence safety

Training Lab stores only:

- pilot and handoff references
- fingerprints of recipient and external reference
- value and currency
- local handoff/reward status
- read-back status
- timestamps and operator ID

It excludes shared secrets, signatures, nonces, raw adapter payloads, raw responses, recipient email, and claim credentials.

## Rollback

Immediately disable new pilot issuing:

```text
TL_STAGE896_LIMITED_PILOT_ENABLED=false
TL_MICROGIFTER_PILOT_ISSUE_ENABLED=false
MG_TRAINING_LAB_PILOT_ISSUE_ENABLED=false
```

Keep the Stage 894 lookup enabled long enough to verify any in-flight or uncertain pilot. Keep the scheduled worker disabled.

## SQL

**No SQL required.**

Stage 896 stores control state in the existing `training_reward_handoffs.metadata_json` and records sanitized evidence in `training_events`.
