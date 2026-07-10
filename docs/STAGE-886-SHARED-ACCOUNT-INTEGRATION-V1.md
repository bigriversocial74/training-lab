# Stage 886 — Shared Microgifter Account Integration v1

## Purpose

Stage 886 lets an authenticated Microgifter user open Training Lab through a short-lived signed identity assertion. Training Lab never receives or stores the user's Microgifter password or password hash.

## SQL migration

Import once:

```text
/database/stage886_shared_account_integration_v1.sql
```

The migration creates:

- `training_account_links`
- `training_auth_nonces`

It does not alter Microgifter user, password, payment, wallet, claim, redemption, or reward tables.

## Required environment configuration

Set these on the deployed Training Lab server:

```text
TL_IDENTITY_SHARED_SECRET=<minimum 32-character random secret>
TL_IDENTITY_ISSUER=microgifter.com
TL_IDENTITY_AUDIENCE=training-lab
TL_IDENTITY_MAX_TTL=180
TL_IDENTITY_CLOCK_SKEW=30
```

Use the exact same shared secret in the Microgifter signing adapter. Never commit the live secret.

## Assertion format

The assertion has three base64url segments:

```text
base64url(header).base64url(payload).base64url(HMAC-SHA256(header.payload, shared_secret))
```

Header:

```json
{"alg":"HS256","typ":"TL-ID"}
```

Required payload claims:

```json
{
  "iss": "microgifter.com",
  "aud": "training-lab",
  "sub": "MICROGIFTER_USER_ID",
  "iat": 1700000000,
  "exp": 1700000120,
  "nonce": "cryptographically-random-single-use-value"
}
```

Optional supported claims:

```json
{
  "jti": "unique-token-id",
  "email": "user@example.com",
  "name": "User Name",
  "role": "participant",
  "merchant": "merchant-context",
  "organization": "organization-context"
}
```

Allowed normalized roles are `participant`, `coach`, `reviewer`, `manager`, and `admin`. Unknown roles are reduced to `participant`.

## Microgifter signing reference

```php
<?php
function mg_base64url(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function mg_training_lab_identity_token(array $user, string $secret): string {
    $now = time();
    $header = ['alg'=>'HS256', 'typ'=>'TL-ID'];
    $payload = [
        'iss'=>'microgifter.com',
        'aud'=>'training-lab',
        'sub'=>(string)$user['id'],
        'email'=>(string)($user['email'] ?? ''),
        'name'=>(string)($user['name'] ?? ''),
        'role'=>(string)($user['role'] ?? 'participant'),
        'merchant'=>(string)($user['merchant_id'] ?? ''),
        'organization'=>(string)($user['organization_id'] ?? ''),
        'iat'=>$now,
        'exp'=>$now + 120,
        'nonce'=>bin2hex(random_bytes(24)),
        'jti'=>bin2hex(random_bytes(16)),
    ];
    $head = mg_base64url(json_encode($header, JSON_THROW_ON_ERROR));
    $body = mg_base64url(json_encode($payload, JSON_THROW_ON_ERROR));
    $signature = mg_base64url(hash_hmac('sha256', $head . '.' . $body, $secret, true));
    return $head . '.' . $body . '.' . $signature;
}
```

Microgifter should POST the assertion to:

```text
/account-link.php
```

using the field:

```text
identity_assertion
```

Machine clients may POST JSON to:

```text
/api/training/account-link.php
```

## Training Lab routes

```text
/account-link.php
/api/training/account-link.php
/admin/account-integration.php
/api/training/account-integration-status.php
/api/training/account-link-revoke.php
```

The diagnostics and revocation routes require a trusted manager/admin session or valid developer key. Revocation immediately invalidates the linked Training Lab session on its next validation.

## Security behavior

- HMAC-SHA256 signature verification.
- Exact issuer and audience matching.
- Short maximum assertion lifetime.
- Clock-skew bounds.
- Single-use nonce stored by SHA-256 hash.
- Transactional nonce consumption with row locking.
- Replay rejection.
- Session ID regeneration after successful handoff.
- Persistent role and account-link state.
- Expiration and revocation checks.
- No browser-supplied role trust.
- No password transfer or auth-table synchronization.

## Validation

```bash
bash ./run-quality-gate.sh
```

The Stage 886 contract test validates valid, tampered, expired, wrong-audience, and excessive-TTL assertions plus schema and replay-control contracts.
