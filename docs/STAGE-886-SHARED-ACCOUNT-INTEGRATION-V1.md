# Stage 886 — Shared Microgifter Account Integration v1

Stage 886 creates a production-safe shared-login boundary between Microgifter and Training Lab.

## What it does

- Microgifter signs a short-lived identity assertion on the server.
- Training Lab verifies the HMAC-SHA256 signature, issuer, audience, timestamps, subject, and one-time token ID.
- A unique database nonce prevents replay.
- Training Lab creates or updates a persistent identity link.
- A trusted Training Lab session is created with an approved normalized role.
- Link revocation, suspension, role synchronization, expiration, and diagnostics are enforced on later requests.

No password or password hash is copied. Training Lab does not write Microgifter authentication tables.

## SQL

Import this file before enabling the bridge secret:

```text
/database/stage886_shared_account_integration_v1.sql
```

Emergency rollback file:

```text
/database/stage886_shared_account_integration_v1_rollback.sql
```

The migration creates:

```text
training_account_links
training_auth_nonces
```

The rollback permanently removes Stage 886 account-link and nonce-audit data.

## Environment configuration

Set the same high-entropy secret on the Microgifter application and Training Lab server:

```text
TL_ACCOUNT_BRIDGE_SECRET=<at least 32 random bytes>
TL_ACCOUNT_BRIDGE_ISSUER=https://microgifter.com
TL_ACCOUNT_BRIDGE_AUDIENCE=training-lab
TL_ACCOUNT_BRIDGE_MAX_TTL=300
TL_ACCOUNT_BRIDGE_CLOCK_SKEW=30
TL_ACCOUNT_BRIDGE_SESSION_TTL=28800
```

Do not commit the secret. `TL_ACCOUNT_BRIDGE_PREVIOUS_SECRET` may temporarily contain the former secret during a controlled rotation.

The assertion TTL and trusted-session TTL are intentionally separate:

- Assertion: normally 180 seconds, maximum 300 seconds.
- Trusted Training Lab session: normally 28,800 seconds (8 hours), configurable from 15 minutes to 24 hours.

## Assertion format

Stage 886 accepts:

```text
v1.<base64url JSON claims>.<base64url HMAC-SHA256 signature>
```

The signature input is exactly:

```text
v1.<base64url JSON claims>
```

Required claims:

```text
iss  trusted Microgifter issuer
 aud  Training Lab audience
 sub  stable Microgifter user ID
 jti  cryptographically random one-time ID
 iat  Unix issued-at timestamp
 exp  Unix expiration timestamp
```

Supported identity/context claims:

```text
email
name
role
merchant_id
organization_id
```

Role aliases are normalized to:

```text
participant
coach
reviewer
manager
admin
```

Unknown roles become `participant`. Browser input is never trusted to elevate a role.

## Microgifter emitter contract

Reference implementation:

```text
/examples/microgifter-stage886-emitter.php
```

The Microgifter application should:

1. Require its normal authenticated user session.
2. Load the authoritative user ID, role, and merchant context on the server.
3. Generate a unique random `jti` for every launch.
4. Sign the assertion with the server-only shared secret.
5. POST the assertion to:

```text
https://labs.microgifter.com/account-link.php
```

The assertion should be posted in a hidden `assertion` field. It must not be generated in JavaScript or expose the shared secret to the browser.

## Training Lab routes

Public signed receiver:

```text
/account-link.php
/api/training/account-link.php
```

Protected manager/admin diagnostics:

```text
/admin/account-integration.php
/api/training/account-integration-status.php
/api/training/account-integration.php
```

The browser and API receivers deliberately do not require same-origin CSRF because the launch originates from Microgifter. Their authorization is the signed, expiring, single-use assertion itself. They still enforce POST, payload limits, request throttling, signature verification, and replay protection.

## Link status behavior

```text
active     trusted sessions may continue until session expiration
suspended  new assertions are rejected and existing sessions are invalidated
revoked    new assertions are rejected and existing sessions are invalidated
```

A manager or administrator can change link status from `/admin/account-integration.php`.

## Deployment order

1. Merge and deploy the Stage 886 code.
2. Preserve the private deployed `/labs/config.php`.
3. Import `database/stage886_shared_account_integration_v1.sql`.
4. Set the Stage 886 environment variables on Training Lab.
5. Configure the identical secret, issuer, and audience on Microgifter.
6. Adapt the reference emitter inside Microgifter.
7. Add an authenticated **Open Training Lab** action in Microgifter.
8. Open `/admin/account-integration.php` and verify configured/schema-ready status.
9. Launch Training Lab from a test Microgifter participant, reviewer, and manager account.
10. Verify replay, expiration, wrong audience, forged signature, suspension, revocation, and logout behavior.

## Secret rotation

1. Place the old secret in `TL_ACCOUNT_BRIDGE_PREVIOUS_SECRET` on Training Lab.
2. Set the new secret as `TL_ACCOUNT_BRIDGE_SECRET` on Training Lab and Microgifter.
3. Confirm new launches report the current secret version.
4. Remove the previous secret after the longest allowed assertion lifetime has passed.

## Safety boundaries

- No passwords or password hashes are copied.
- No direct Microgifter authentication-table writes.
- No payment processing.
- No wallet balance mutation.
- No claim or redemption mutation.
- No reward issuance.
- No destructive Microgifter synchronization.
- SQL changes are limited to the two dedicated Training Lab identity/audit tables.

## Validation

```bash
bash ./run-quality-gate.sh
```

The Stage 886 contract test covers signature validation, role normalization, audience enforcement, expiration, maximum TTL, SQL table contracts, unique nonce constraints, session regeneration, trusted-source tagging, legacy fallback closure, route presence, and CI integration.
