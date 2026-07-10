# Stage 894 — Signed Reward Lookup Client v1

Stage 894 connects Training Lab Stage 893 to Microgifter's signed read-only reward lookup endpoint.

## Purpose

Stage 893 quarantines a handoff when an adapter may have succeeded after its worker lost the processing lease. Stage 894 allows Training Lab to ask Microgifter whether the canonical Microgift already exists before local state is repaired.

The client does not issue, claim, redeem, cancel, refund, replace, or transfer rewards.

## Training Lab configuration

Keep the client disabled during deployment:

```text
TL_MICROGIFTER_REWARD_LOOKUP_ENABLED=false
TL_MICROGIFTER_REWARD_LOOKUP_URL=https://microgifter.com/api/integrations/training-lab-reward-lookup.php
TL_MICROGIFTER_REWARD_LOOKUP_SECRET=<minimum 32-character random shared secret>
TL_MICROGIFTER_REWARD_LOOKUP_ALLOWED_HOSTS=microgifter.com,www.microgifter.com
TL_MICROGIFTER_REWARD_LOOKUP_TIMEOUT_SECONDS=8
TL_MICROGIFTER_REWARD_LOOKUP_CONNECT_TIMEOUT_SECONDS=3
TL_MICROGIFTER_REWARD_LOOKUP_MAX_RESPONSE_BYTES=131072
```

Equivalent private `/labs/config.php` values:

```php
'microgifter_reward_lookup_enabled' => false,
'microgifter_reward_lookup_url' => 'https://microgifter.com/api/integrations/training-lab-reward-lookup.php',
'microgifter_reward_lookup_allowed_hosts' => ['microgifter.com', 'www.microgifter.com'],
'microgifter_reward_lookup_timeout_seconds' => 8,
'microgifter_reward_lookup_connect_timeout_seconds' => 3,
'microgifter_reward_lookup_max_response_bytes' => 131072,
```

Store the shared secret in the server environment when possible. Never commit it.

## Signing contract

The client sends:

```text
X-Microgifter-Training-Lab-Timestamp
X-Microgifter-Training-Lab-Nonce
X-Microgifter-Training-Lab-Signature
```

The canonical signing input is:

```text
training-lab-reward-lookup-v1\n
<timestamp>\n
<nonce>\n
<sha256 raw JSON body>
```

The signature is HMAC-SHA256 using the shared Stage 894 secret.

Each request receives a 48-character hexadecimal nonce generated with `random_bytes(24)`.

## Transport boundaries

The client requires:

- HTTPS
- an exact server-controlled host allowlist
- no URL username or password
- no URL fragment
- TLS peer verification
- TLS hostname verification
- redirects disabled
- HTTPS-only cURL protocols when supported
- bounded connection and total timeouts
- bounded response size
- JSON response validation

The client sends no browser cookie, Training Lab session, Microgifter session, password, identity assertion, developer key, or shared account token.

## Conditional adapter registration

Stage 893 discovers read adapters through `function_exists()`.

Stage 894 registers:

```php
microgifter_training_reward_lookup(array $payload): array
```

only when all of these are true:

1. the feature flag is enabled
2. the endpoint is HTTPS
3. the endpoint host is allowlisted
4. the URL contains no credentials or fragment
5. the shared secret is at least 32 characters
6. PHP cURL is available

Incomplete configuration leaves the adapter unregistered, so Stage 893 remains fail-closed and quarantined deliveries stay blocked.

## Operator visibility

The Reward Bridge displays a Stage 894 panel with:

- enabled/disabled state
- ready/not-ready state
- endpoint hostname and allowlist status
- shared-secret present/missing status
- cURL availability through the API summary

The shared secret is never displayed or returned.

Protected APIs include a sanitized `client` or `reward_lookup_client` summary:

```text
/api/training/reward-delivery-reconciliation.php
/api/training/reward-handoff-operations.php
/api/training/reward-handoff-outbox.php
/api/training/app-action.php
/api/training/proof-review-workflow.php
```

The CLI worker also reports the sanitized Stage 894 readiness summary.

## Deployment order

1. Merge and deploy the Microgifter Stage 894 endpoint.
2. Keep both Microgifter and Training Lab Stage 894 feature flags disabled.
3. Generate one shared random secret of at least 32 characters.
4. Configure the same secret on both servers.
5. Deploy the Training Lab Stage 894 client.
6. Confirm the Reward Bridge Stage 894 panel shows the correct endpoint and secret presence while still disabled.
7. Enable the Microgifter lookup endpoint only.
8. Run signed acceptance tests for valid, tampered, expired, replayed, wrong-user, missing, and found requests.
9. Enable the Training Lab Stage 894 client.
10. Confirm Stage 893 reports the read adapter as ready.
11. Enable Stage 893 reconciliation only after acceptance passes.
12. Keep production reward processing disabled until complete adapter acceptance is approved.

## Rollback

Disable the Training Lab client:

```text
TL_MICROGIFTER_REWARD_LOOKUP_ENABLED=false
```

Disable the Microgifter endpoint:

```text
MG_TRAINING_LAB_REWARD_LOOKUP_ENABLED=false
```

Stage 893 will immediately return to adapter-missing fail-closed behavior. Quarantined deliveries remain blocked and no reward state is deleted.

## SQL

**No SQL required.**

The Training Lab client adds no tables or columns. The Microgifter endpoint reuses the existing Microgift, PPPM, idempotency, and audit tables.
