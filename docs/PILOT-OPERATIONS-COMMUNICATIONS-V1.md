# Pilot Operations + Communications v1

## Outcome

Training Lab now has a controlled, owner-scoped communication layer for participant invitations, task reminders, proof status, review outcomes, reward eligibility, and reward-delivery updates.

The module adds an idempotent notification outbox, delivery attempts, merchant template overrides, participant reminder preferences, administrator suppressions, campaign pilot limits, engagement reporting, and an adapter-gated worker.

It does not create another user, contact, role, wallet, payment, gift, claim, redemption, or Microgifter delivery system.

## SQL migration

Back up the `ywzyeite_microlabs` database, then import:

```text
database/pilot_operations_communications_v1.sql
```

The migration creates:

- `training_notification_templates`;
- `training_notification_preferences`;
- `training_notification_suppressions`;
- `training_pilot_controls`;
- `training_notification_outbox`;
- `training_notification_attempts`.

It also inserts immutable global email templates. Merchant changes are saved as owner-scoped overrides and never overwrite the system rows.

The migration is additive. It does not alter Microgifter account, password, wallet, payment, gift, claim, redemption, or marketing-preference tables.

## Recipient authority

Recipients are resolved at synchronization and delivery time from an active `training_account_links` row:

```text
training_participants.user_id
    → training_account_links.training_user_id
    → active account-link email
```

Training Lab does not create a second email address book. Operational screens store and display only a SHA-256 confirmation hash.

## Event synchronization

Run synchronization from the administrator or merchant dashboard, or from CLI:

```bash
php ./bin/notification-worker.php --sync --limit=250
```

Include one reminder per participant/task/day:

```bash
php ./bin/notification-worker.php --sync --include-reminders --limit=250
```

Supported events:

- participant invited;
- task reminder;
- proof submitted;
- proof approved;
- proof revision requested;
- proof rejected;
- reward earned;
- reward delivery succeeded;
- reward delivery failed.

Every event receives a stable SHA-256 idempotency key. Re-running synchronization does not create duplicates.

## Campaign pilot controls

Each campaign has independent controls:

- `draft`, `active`, `paused`, or `completed` status;
- email enabled or disabled;
- maximum pilot participants;
- daily notification limit;
- pause reason.

A notification is queued only when:

1. the campaign belongs to the signed-in merchant or the operator is an administrator;
2. the campaign pilot is active;
3. campaign email is enabled;
4. participant and daily limits are not exceeded;
5. an active account link has a valid email;
6. the recipient is not suppressed;
7. the recipient preference permits the message class;
8. an active system or merchant template exists.

## Provider adapter

There is no PHP `mail()` fallback. Real delivery requires an application adapter:

```php
function training_lab_send_notification_email(array $payload): array
{
    // Send through the approved provider outside this module.
    return [
        'ok' => true,
        'message_id' => 'provider-message-id',
        'code' => 'accepted',
    ];
}
```

The payload contains only:

- `to`;
- `subject`;
- plain-text `text`;
- the outbox `idempotency_key`;
- safe notification metadata.

It must not contain passwords, cookies, authorization headers, wallet instructions, claim codes, raw provider credentials, or Microgifter API secrets.

Only a SHA-256 hash of the returned provider message ID is stored. Raw provider responses are not persisted.

## Deployment configuration

Keep these values disabled for the first deployment:

```php
'notification_delivery_enabled' => false,
'notification_worker_enabled' => false,
'notification_provider' => 'adapter',
'notification_batch_size' => 10,
'notification_max_attempts' => 5,
'notification_retry_base_seconds' => 300,
'notification_lease_seconds' => 300,
'notification_worker_actor_user_id' => 1,
'public_base_url' => 'https://labs.microgifter.com',
```

Set the unsubscribe signing secret through a protected environment variable when possible:

```bash
export TL_NOTIFICATION_UNSUBSCRIBE_SECRET='a-long-random-secret-at-least-32-characters'
```

Do not commit the real secret.

## Safe activation order

1. Back up application files and `ywzyeite_microlabs`.
2. Import the Section 15 migration.
3. Deploy the accepted application files while preserving `/labs/config.php`.
4. Run syntax and the complete quality gate.
5. Open `/admin/pilot-communications.php` and verify the schema is ready.
6. Create one small campaign pilot with a low participant and daily limit.
7. Save or preview templates.
8. Synchronize events while global delivery remains disabled.
9. Review queued, blocked, and suppressed items.
10. Connect and test the approved provider adapter in a non-production environment.
11. Set a strong unsubscribe secret.
12. Enable `notification_worker_enabled` only.
13. Confirm the worker still fails closed because delivery is disabled.
14. Enable `notification_delivery_enabled` for the controlled pilot.
15. Process a batch of one and verify the attempt ledger.
16. Increase batch size only after successful pilot evidence.

## Worker processing

Process the configured batch:

```bash
php ./bin/notification-worker.php --process --limit=10
```

Synchronize and process in one invocation:

```bash
php ./bin/notification-worker.php --sync --process --limit=10
```

The worker fails closed unless:

- the notification schema is installed;
- global delivery is enabled;
- the worker is enabled;
- `training_lab_send_notification_email()` exists;
- the campaign pilot is active and email-enabled;
- the recipient account link and preferences remain valid.

Retries use exponential backoff capped at 24 hours. Row locks and lease hashes prevent concurrent processing of the same item.

## Reminder preferences

Reminder templates include a signed, 30-day preference link. The public preference page:

- performs no mutation on GET;
- requires POST, CSRF, same-origin validation, and rate limiting for changes;
- changes only Training Lab reminder-class notifications;
- never displays the email address or account-link identifier;
- does not modify Microgifter marketing preferences.

Proof, review, reward, and delivery updates are treated as transactional messages and remain separate from optional reminders.

## Suppressions and incidents

Administrators can add suppressions for:

- hard bounce;
- complaint;
- invalid recipient;
- policy;
- manual operational action.

The database stores an email hash rather than the address. The incident queue shows sanitized error codes and details, attempt status, provider name, response code, and hash confirmations only.

## Merchant reporting

`/admin/pilot-reporting.php` reports:

- participants;
- active and completed participants;
- participants with verified task receipts;
- engagement rate;
- completion rate;
- proof and review totals;
- reward totals;
- notification delivery rate;
- open communication incidents.

It does not add tracking pixels, read receipts, cross-site tracking, or a second analytics identity.

## Rollback

1. Disable `notification_delivery_enabled` and `notification_worker_enabled`.
2. Pause every active campaign pilot.
3. Stop the notification worker or cron entry.
4. Restore the previous application files while preserving `/labs/config.php`.
5. Keep outbox, attempt, preference, and suppression rows for audit.
6. Do not delete delivered notification history.
7. Re-run syntax, quality, product acceptance, and live acceptance.
8. Re-enable only after the provider and pilot issue is understood.

The Section 15 tables may remain in place during application rollback. Destructive schema rollback is not required.

## Quality validation

```bash
bash ./run-full-syntax-check.sh
bash ./run-quality-gate.sh
php ./tests/pilot-operations-communications-contract-test.php
php ./scripts/pilot-operations-communications-quality-audit.php
```

## Authority boundary

Pilot communications write only to the six Section 15 Training Lab tables and the existing Training Lab event ledger. They do not send until an external adapter and every explicit gate are enabled. They do not call Microgifter APIs or mutate accounts, passwords, wallets, payments, gifts, claims, redemptions, rewards, or reward handoffs.
