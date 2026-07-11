# Resend Webhooks + Delivery Reconciliation v1

## Purpose

Section 17 closes the gap between an email being accepted by Resend and its final delivery outcome. It receives signed provider events, rejects forged or stale requests, ignores duplicate deliveries, reconciles out-of-order events, and protects recipients after permanent bounces, complaints, or provider suppressions.

This module does not send email, activate campaign delivery, start workers, change campaign settings, create accounts, issue rewards, modify wallets, or call Microgifter APIs.

## SQL required

Back up the Training Lab database, then import once:

```text
database/notification_provider_webhooks_v1.sql
```

Import it after:

```text
database/pilot_operations_communications_v1.sql
```

The migration creates:

- `training_notification_provider_events`
- `training_notification_provider_states`

It is additive. It does not alter Microgifter user, password, payment, wallet, gift, claim, redemption, marketing-preference, or reward-delivery tables.

## Security contract

Resend webhook requests are verified with the raw HTTP request body and these headers:

- `svix-id`
- `svix-timestamp`
- `svix-signature`

The signature input is:

```text
svix-id.svix-timestamp.raw-body
```

The endpoint computes HMAC-SHA256 with the base64-decoded portion of the `whsec_` signing secret and accepts a matching `v1` signature using constant-time comparison.

Additional protections:

- POST only
- JSON content type
- configurable request-size limit
- configurable timestamp tolerance
- unique SHA-256 hash of `svix-id`
- unique SHA-256 payload hash
- replay-safe duplicate acknowledgement
- JSON parsing only after signature verification
- database transaction and row locks during reconciliation
- no raw webhook payload storage
- no recipient-address storage
- no provider signing-secret storage in the database
- no full provider message ID storage
- no outbound HTTP request from the webhook endpoint

## Supported events

Register only these email events in the Resend webhook configuration:

```text
email.sent
email.delivered
email.delivery_delayed
email.bounced
email.complained
email.failed
email.suppressed
```

Other event types are safely acknowledged and recorded as ignored. Open and click tracking events are intentionally not required for Training Lab delivery reconciliation.

## Delivery correlation

Section 16 stores only a SHA-256 hash of the successful Resend email ID in `training_notification_outbox.provider_message_hash`.

Section 17 hashes the webhook `data.email_id` and uses the hash to find the outbox item. The full provider ID is never persisted.

If no matching outbox item exists, the event is stored as `orphaned` for administrator investigation. It does not create a suppression or mutate an unrelated notification.

## Out-of-order handling

Resend can deliver events more than once and not necessarily in order.

Each event stores its provider occurrence timestamp and precedence. Reconciliation applies a new event only when it occurred after the currently reconciled event, or when it has a stronger precedence at the same timestamp. Older events remain in the immutable ledger but cannot downgrade the current state.

## Reconciliation behavior

| Provider status | Outbox behavior | Suppression behavior |
|---|---|---|
| sent | accepted state retained | none |
| delayed | accepted state retained; operational delay noted | none |
| delivered | delivered state and timestamp confirmed | none |
| failed | terminal failure; no automatic retry time | none |
| suppressed | outbox suppressed | hashed policy suppression |
| bounced | outbox suppressed | hashed hard-bounce suppression |
| complained | outbox suppressed | hashed complaint suppression |

The provider is responsible for continuing delivery after a temporary delay. Training Lab does not resend a delayed message and risk duplicate delivery.

## Configuration

Prefer protected server environment variables:

```bash
TL_RESEND_WEBHOOK_ENABLED=false
TL_RESEND_WEBHOOK_SECRET=whsec_REPLACE_WITH_PROTECTED_SECRET
TL_RESEND_WEBHOOK_TOLERANCE_SECONDS=300
TL_RESEND_WEBHOOK_MAX_BODY_BYTES=262144
TL_PUBLIC_BASE_URL=https://labs.microgifter.com
```

The active private `/labs/config.php` may use equivalent `training_lab` keys:

```php
'resend_webhook_enabled' => false,
'resend_webhook_tolerance_seconds' => 300,
'resend_webhook_max_body_bytes' => 262144,
// 'resend_webhook_secret' => 'DO_NOT_COMMIT_A_REAL_SECRET',
```

Do not commit the real signing secret.

## Safe activation order

1. Back up application files and database.
2. Deploy the verified `main` package while preserving `/labs/config.php`.
3. Import `database/notification_provider_webhooks_v1.sql`.
4. Run the complete syntax and quality gates.
5. Configure the protected `TL_RESEND_WEBHOOK_SECRET`.
6. Keep `TL_RESEND_WEBHOOK_ENABLED=false`.
7. Run the readiness check:

```bash
php ./bin/webhook-reconciliation-check.php
```

8. Register the production HTTPS endpoint in Resend:

```text
https://labs.microgifter.com/api/webhooks/resend.php
```

9. Select only the supported delivery lifecycle events.
10. Set `TL_RESEND_WEBHOOK_ENABLED=true`.
11. Send one fixed administrator provider test from Section 16.
12. Confirm `email.sent` and `email.delivered` appear in `/admin/email-webhooks.php`.
13. Confirm the event correlates to the expected short provider-message hash.
14. Keep campaign delivery and the worker disabled until webhook acceptance is clean.

## Validation

```bash
bash ./run-full-syntax-check.sh
bash ./run-quality-gate.sh
php ./bin/product-acceptance.php
php ./bin/webhook-reconciliation-check.php --json
```

The webhook endpoint itself should return HTTP 200 for valid, duplicate, ignored, and orphaned signed events. Invalid signatures, stale timestamps, missing schema, disabled ingestion, invalid JSON, oversized bodies, and unsupported methods fail closed with non-200 responses.

## Administrator diagnostics

Open:

```text
/admin/email-webhooks.php
```

The dashboard shows:

- webhook configuration readiness
- endpoint URL
- received and reconciled totals
- duplicate count
- orphaned correlations
- automatic suppression count
- sanitized recent event ledger
- latest reconciled delivery state per outbox item

It does not display email addresses, raw payloads, subjects, senders, signing secrets, authorization headers, or full provider message IDs.

## Incident response

### Orphaned events

1. Keep campaign delivery paused.
2. Compare the short provider-message confirmation against the Section 15 attempt ledger.
3. Confirm the event came from the current Resend project and webhook endpoint.
4. Confirm Section 16 stored a provider message hash for the accepted send.
5. Replay the event from Resend only after the correlation defect is corrected.

### Signature failures

1. Keep the webhook gate disabled if failures are persistent.
2. Confirm the webhook signing secret belongs to the exact production endpoint.
3. Confirm no proxy or application layer changes the raw body before verification.
4. Confirm server time is synchronized.
5. Rotate the webhook signing secret if exposure is suspected.

### Bounce or complaint spike

1. Disable campaign delivery and the notification worker.
2. Pause active campaign pilots.
3. Review automatic hashed suppressions.
4. Confirm sender-domain authentication and list quality.
5. Resume only after the incident cause is documented and corrected.

## Rollback

1. Set:

```bash
TL_RESEND_WEBHOOK_ENABLED=false
TL_NOTIFICATION_DELIVERY_ENABLED=false
TL_NOTIFICATION_WORKER_ENABLED=false
```

2. Remove or disable the endpoint in the Resend dashboard.
3. Stop notification cron processing.
4. Pause active campaign pilots.
5. Restore the previous application package while preserving `/labs/config.php`.
6. Retain provider-event, provider-state, outbox, attempt, preference, and suppression records for audit.
7. Rotate the signing secret if compromise is suspected.

Destructive database rollback is not required. The Section 17 tables are additive and can remain read-only after rollback.
