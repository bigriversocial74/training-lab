# Stage 889 — Shared Account Session Hardening v1

## Purpose

Stage 889 separates the short-lived Microgifter identity assertion from the authenticated Training Lab session created after the assertion is accepted.

The assertion remains a single-use handoff credential. It is not the Training Lab session.

## Problem repaired

Stage 886 originally copied the assertion expiration into the account-link and trusted-session expiration fields. With a normal 120-second assertion, a successful Training Lab login could therefore become invalid about two minutes later.

Stage 889 changes the lifecycle to:

1. Microgifter creates a short-lived signed assertion.
2. Training Lab verifies and consumes the assertion once.
3. Training Lab creates or refreshes a persistent account link.
4. Training Lab creates a trusted session with its own absolute and idle deadlines.
5. Every authenticated request rechecks the account-link status.
6. Revoked or suspended links immediately lose access on the next request.

## Configuration

Training Lab continues to require:

```text
TL_IDENTITY_SHARED_SECRET=<minimum 32-character random secret>
TL_IDENTITY_ISSUER=microgifter.com
TL_IDENTITY_AUDIENCE=training-lab
TL_IDENTITY_MAX_TTL=180
TL_IDENTITY_CLOCK_SKEW=30
```

Stage 889 adds:

```text
TL_IDENTITY_SESSION_TTL=28800
TL_IDENTITY_SESSION_IDLE_TTL=3600
```

Defaults:

- Absolute trusted-session lifetime: 28,800 seconds / 8 hours.
- Idle timeout: 3,600 seconds / 1 hour.
- Set `TL_IDENTITY_SESSION_IDLE_TTL=0` to disable idle expiration while retaining the absolute timeout.

The deployed private `/labs/config.php` may use these equivalent keys inside the `training_lab` section:

```php
'identity_session_ttl_seconds' => 28800,
'identity_session_idle_ttl_seconds' => 3600,
```

Environment variables take precedence. Never commit the live shared secret.

## Persistent account links

`training_account_links.expires_at` is no longer populated with the assertion expiration. Successful handoffs clear legacy assertion-derived values from this nullable column.

An account link remains active until it is explicitly:

- revoked; or
- suspended.

The existing Stage 886 schema supports this behavior. No migration is required.

## Trusted session behavior

The signed session stores separate values for:

- assertion issued time;
- assertion expiration;
- session start time;
- session absolute expiration;
- session last activity time; and
- session idle timeout.

The assertion may expire while the authenticated Training Lab session remains valid.

A fresh valid assertion creates a fresh trusted session and rotates the PHP session ID.

## Global validation

Every signed-session request lazy-loads the Stage 886 database-backed validator, including pages that initially load only the base auth gate.

Validation checks:

- trusted-session absolute timeout;
- trusted-session idle timeout;
- account-link existence;
- account-link status;
- current synchronized role;
- current merchant context; and
- current organization context.

If the database, schema, account link, or active link status cannot be confirmed, the signed session is cleared.

## Legacy session boundary

Once `TL_IDENTITY_SHARED_SECRET` is configured, Training Lab no longer trusts loose legacy session fields such as `user_id`, `mg_user_id`, or `is_admin` as proof of a Microgifter identity.

The signed assertion receiver is the production trust boundary.

## Diagnostics

Manager/admin diagnostics:

```text
/admin/account-integration.php
/api/training/account-integration-status.php
```

Diagnostics show:

- schema readiness;
- shared identity readiness;
- assertion maximum TTL;
- clock skew;
- absolute session TTL;
- idle timeout;
- current assertion expiration;
- current session start and expiration;
- current last activity;
- active/revoked/suspended link counts; and
- recent linked identities.

The shared secret is never returned.

## Microgifter compatibility

Microgifter Stage 887 remains compatible and does not require a code change. It should continue issuing assertions with a 60–180 second lifetime and POSTing them as `identity_assertion` to:

```text
https://labs.microgifter.com/account-link.php
```

Microgifter does not control the resulting Training Lab session lifetime.

## Validation

Run:

```bash
bash ./run-quality-gate.sh
```

The Stage 889 contract verifies:

- session configuration;
- assertion/session lifetime separation;
- survival after assertion expiration;
- absolute session expiration;
- idle expiration;
- fresh-session creation;
- unknown-role downgrade;
- credential exclusion;
- persistent link behavior;
- global validator loading; and
- diagnostic/configuration contracts.

## SQL

No SQL required.

The previously imported migration remains the only shared-account migration:

```text
database/stage886_shared_account_integration_v1.sql
```
