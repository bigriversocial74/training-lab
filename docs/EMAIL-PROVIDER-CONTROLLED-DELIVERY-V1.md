# Email Provider + Controlled Delivery v1

## Purpose

Section 16 supplies the first concrete Training Lab email provider while preserving every Section 15 outbox, preference, suppression, ownership, pilot, worker, and privacy boundary.

The built-in provider is **Resend**. Training Lab sends plain-text email through the fixed endpoint:

```text
https://api.resend.com/emails
```

The endpoint is not configurable. The adapter does not follow redirects, verifies TLS, limits connection/runtime duration, caps response bytes, and sends a stable `Idempotency-Key` for normal outbox deliveries.

## No SQL required

Section 16 uses the existing Section 15 tables:

- `training_notification_templates`
- `training_notification_preferences`
- `training_notification_suppressions`
- `training_pilot_controls`
- `training_notification_outbox`
- `training_notification_attempts`

Import `database/pilot_operations_communications_v1.sql` only if those tables are not already present.

## Provider configuration

Prefer protected server environment variables:

```bash
export TL_NOTIFICATION_PROVIDER='resend'
export TL_RESEND_API_KEY='re_REPLACE_WITH_PROTECTED_KEY'
export TL_NOTIFICATION_FROM_EMAIL='training@verified-domain.example'
export TL_NOTIFICATION_FROM_NAME='Microgifter Training Lab'
export TL_NOTIFICATION_REPLY_TO='support@verified-domain.example'
export TL_NOTIFICATION_TEST_RECIPIENT='verified-admin@example.com'
export TL_NOTIFICATION_TEST_DELIVERY_ENABLED='false'
export TL_NOTIFICATION_DELIVERY_ENABLED='false'
export TL_NOTIFICATION_WORKER_ENABLED='false'
```

Do not commit the API key, recipient, or unsubscribe secret. The sender address must use a domain verified by the provider before delivery to general recipients.

## Initial validation

Deploy the application with all delivery gates disabled, then run:

```bash
bash ./run-full-syntax-check.sh
bash ./run-quality-gate.sh
php ./bin/product-acceptance.php
php ./bin/email-provider-check.php
```

The provider check reports only:

- selected provider;
- configuration readiness;
- whether the fixed test gate is enabled;
- whether campaign processing is enabled;
- safe diagnostic booleans and confirmation hashes.

It never prints the API key, sender address, reply-to address, test recipient, Authorization header, raw provider response, or full provider message ID.

## Controlled administrator test

1. Verify the Resend domain and sender address.
2. Configure a single administrator-controlled `TL_NOTIFICATION_TEST_RECIPIENT`.
3. Set only:

```bash
export TL_NOTIFICATION_TEST_DELIVERY_ENABLED='true'
```

4. Keep campaign delivery and the worker disabled.
5. Open `/admin/email-provider.php` as an administrator.
6. Review every diagnostic.
7. Use **Send Provider Test** once.

The form accepts no recipient address. It sends a fixed, non-participant test message containing no campaign, proof, reward, wallet, gift, claim, password, cookie, or provider credential data.

CLI equivalent:

```bash
php ./bin/email-provider-check.php --test
```

The test requires the same protected fixed recipient and test-delivery gate.

## Provider response handling

Successful responses:

- require a provider message ID;
- store only a SHA-256 hash of that ID in the outbox/attempt ledger;
- store the normalized response code `accepted`;
- never store the raw response.

Retryable failures include:

- connection/DNS/timeout/send/receive transport failures;
- HTTP 408, 425, and 429;
- provider 5xx responses;
- concurrent idempotent request responses.

Permanent failures include:

- invalid or missing API credentials;
- invalid/unverified sender domain;
- invalid recipient, subject, body, or idempotency key;
- unsupported provider configuration;
- invalid idempotent request payload conflicts;
- other provider 4xx validation/policy failures.

Retryable failures receive the existing exponential-backoff schedule. Permanent failures remain in `failed` state with no `next_attempt_at`, so the worker does not loop them. An authorized operator may correct the configuration and explicitly retry the notification.

## Campaign activation order

A successful administrator test does **not** enable campaign email.

Activate in this order:

1. Confirm Section 15 SQL is imported.
2. Confirm provider diagnostics are fully configured.
3. Send and receive one administrator test.
4. Review `/admin/notification-incidents.php`.
5. Activate one campaign pilot with a small participant and daily limit.
6. Enable that campaign's email control.
7. Set `TL_NOTIFICATION_DELIVERY_ENABLED=true`.
8. Set `TL_NOTIFICATION_WORKER_ENABLED=true` only for the controlled worker runtime.
9. Process a small batch and review attempts before scheduling recurring execution.

Every send still requires:

- trusted account-link recipient resolution;
- an active campaign pilot;
- campaign email enabled;
- participant and daily pilot limits;
- active recipient preferences;
- no active suppression;
- an active template;
- global delivery enabled;
- worker enabled;
- configured provider adapter.

## Privacy and authority boundaries

Section 16 does not:

- create or update Microgifter users;
- create contact or marketing lists;
- change Microgifter communication preferences;
- issue gifts or rewards;
- claim or redeem rewards;
- change wallets or payments;
- contact Microgifter APIs;
- store recipient addresses in provider diagnostics or attempts;
- store raw provider responses;
- store full provider message IDs;
- use PHP `mail()`;
- permit arbitrary provider URLs;
- permit arbitrary administrator test recipients.

## Rollback

1. Set these gates to false:

```bash
export TL_NOTIFICATION_WORKER_ENABLED='false'
export TL_NOTIFICATION_DELIVERY_ENABLED='false'
export TL_NOTIFICATION_TEST_DELIVERY_ENABLED='false'
```

2. Stop any notification worker/cron process.
3. Pause active campaign pilot controls.
4. Restore the previous application package while preserving `/labs/config.php`.
5. Keep outbox and attempt records for audit.
6. Rotate the Resend API key if credential exposure is suspected.

No schema rollback is needed.
