# Limited Live Email Pilot + Graduation v1

Section 18 moves Training Lab email from a fixed administrator test into one tightly bounded participant pilot. It does not open unrestricted campaign delivery.

## Authority boundary

Section 18 owns only Training Lab pilot-run records, cohort snapshots, acceptance checks, an operations timeline, and a bounded caller around the existing notification processor.

It does not:

- create users or contact records;
- change passwords, sessions, or Microgifter account authority;
- change wallets, payments, gifts, claims, redemptions, or reward delivery;
- bypass notification preferences or suppressions;
- expose recipient addresses, API keys, webhook secrets, raw provider payloads, or full provider message IDs;
- enable the unrestricted notification worker;
- add another provider or another email outbox.

The existing Training Lab outbox, attempts, Resend adapter, signed webhook ledger, current provider states, preferences, suppressions, and campaign pilot controls remain authoritative.

## SQL required

Import in this order:

```text
database/pilot_operations_communications_v1.sql
database/notification_provider_webhooks_v1.sql
database/limited_email_pilot_graduation_v1.sql
```

The Section 18 migration creates:

```text
training_notification_pilot_runs
training_notification_pilot_members
training_notification_pilot_checks
training_notification_pilot_events
```

The migration is additive. Destructive rollback is not required.

## Disabled-first configuration

Keep every gate disabled during deployment:

```bash
TL_LIMITED_EMAIL_PILOT_ENABLED=false
TL_LIMITED_EMAIL_PILOT_PROCESSING_ENABLED=false
TL_NOTIFICATION_DELIVERY_ENABLED=false
TL_NOTIFICATION_WORKER_ENABLED=false
```

The unrestricted notification worker must remain disabled for the entire Section 18 pilot.

Recommended limits:

```bash
TL_LIMITED_EMAIL_PILOT_MAXIMUM_COHORT=10
TL_LIMITED_EMAIL_PILOT_MAXIMUM_BATCH=3
TL_LIMITED_EMAIL_PILOT_WEBHOOK_TIMEOUT_SECONDS=900
TL_LIMITED_EMAIL_PILOT_DELAY_TIMEOUT_SECONDS=1800
TL_LIMITED_EMAIL_PILOT_MINIMUM_DELIVERY_RATE_PERCENT=100
TL_LIMITED_EMAIL_PILOT_ACTOR_USER_ID=1
```

## Prerequisites

Before creating a run:

1. Deploy the latest verified package while preserving `/labs/config.php`.
2. Import the Section 15, Section 17, and Section 18 migrations.
3. Configure the Resend API key and a verified sender domain.
4. Configure the fixed administrator test recipient.
5. Ensure the fixed test recipient matches an active `training_account_links` record.
6. Configure the signed Resend webhook endpoint and `whsec_` secret.
7. Verify `/admin/email-provider.php` is ready.
8. Verify `/admin/email-webhooks.php` is ready.
9. Confirm `TL_NOTIFICATION_WORKER_ENABLED=false`.
10. Run the complete syntax, quality, and product acceptance commands.

## Safe activation order

### 1. Enable orchestration only

```bash
TL_LIMITED_EMAIL_PILOT_ENABLED=true
TL_LIMITED_EMAIL_PILOT_PROCESSING_ENABLED=false
TL_NOTIFICATION_DELIVERY_ENABLED=false
TL_NOTIFICATION_WORKER_ENABLED=false
```

Open:

```text
/admin/email-pilot.php
```

Create one pilot for one campaign. Only one open Section 18 run is allowed at a time.

### 2. Snapshot a bounded cohort

Choose a cohort of 1–10 participants. The service selects only participants with:

- an invited or active campaign status;
- an active Training Lab account link;
- a valid linked email address;
- no active suppression.

Only account-link IDs and SHA-256 recipient hashes are stored in the pilot member snapshot. Addresses are never displayed by Section 18.

### 3. Send the fixed administrator canary

Enable the Section 16 fixed-recipient test gate and Section 17 webhook gate. Do not enable campaign delivery.

The canary:

- uses the protected fixed administrator test recipient;
- requires that address to match an active Training Lab account link;
- is recorded in the existing notification outbox and attempt ledger;
- contains no participant, proof, reward, wallet, claim, or credential data;
- waits for the signed webhook to report final `delivered` status.

Provider acceptance is not sufficient. The run cannot be approved until `training_notification_provider_states` confirms `delivered` for the canary outbox item.

### 4. Approve the pilot

The approval checkpoint requires:

- Section 18 enabled;
- unrestricted worker disabled;
- Resend configured;
- signed webhook reconciliation ready;
- canary delivery confirmed;
- cohort between 1 and 10.

Approval writes an immutable scored check group and an append-only pilot event.

### 5. Enable bounded participant processing

After approval:

```bash
TL_LIMITED_EMAIL_PILOT_PROCESSING_ENABLED=true
TL_NOTIFICATION_DELIVERY_ENABLED=true
TL_NOTIFICATION_WORKER_ENABLED=false
```

Start the participant pilot from `/admin/email-pilot.php`.

Starting the run activates only the selected campaign's `training_pilot_controls` record with the run's cohort and daily limits.

### 6. Process one bounded batch

Use the administrator button or CLI:

```bash
php ./bin/limited-email-pilot-worker.php \
  --run=RUN_PUBLIC_ID \
  --limit=1 \
  --json
```

The worker:

- accepts only an explicit run;
- processes only outbox rows for active pilot members;
- excludes the administrator canary;
- caps every invocation at three messages;
- enforces the run's daily limit;
- temporarily opens the existing processor only inside the bounded invocation;
- restores the unrestricted worker environment immediately afterward;
- pauses automatically if health thresholds fail.

Do not schedule `bin/notification-worker.php` during Section 18.

## Automatic pause conditions

A running pilot is paused and its campaign email control is disabled when any of these occur:

- permanent bounce;
- spam complaint;
- provider terminal failure;
- provider suppression;
- orphaned signed webhook event;
- provider-accepted message without webhook confirmation after the timeout;
- delayed message that remains delayed beyond the delay timeout.

The pilot event timeline records the automatic stop without recipient or provider-secret data.

## Monitoring

Read-only CLI:

```bash
php ./bin/limited-email-pilot-check.php --run=RUN_PUBLIC_ID --json
```

Administrator pages:

```text
/admin/email-pilot.php
/admin/email-webhooks.php
/admin/notification-incidents.php
```

Review after every batch:

- provider-accepted count;
- webhook-confirmed delivered count;
- delivery rate;
- missing webhook confirmations;
- delayed and stale-delayed messages;
- bounces;
- complaints;
- provider failures and suppressions;
- orphaned events;
- pilot timeline.

## Graduation

Run **Evaluate Graduation** after at least one participant message has been accepted and reconciled.

Graduation requires every check to pass:

- Section 18 remains enabled;
- unrestricted worker remains disabled;
- provider and webhook are ready;
- administrator canary is delivered;
- cohort remains bounded;
- at least one participant message is provider-accepted;
- no participant message is missing webhook confirmation;
- no health threshold is breached;
- webhook-confirmed delivery rate meets the configured requirement.

Graduation:

- records a final immutable check group;
- writes a graduation timeline event;
- marks pilot members completed;
- marks campaign pilot controls completed;
- disables campaign email for the finished pilot.

Graduation does not enable unrestricted delivery. A later production rollout must be a separately approved phase.

## Rejection

An administrator may reject a pilot at any time with a required reason. Rejection disables the campaign's email control and retains all audit records.

## Emergency stop

Use any of the following:

```bash
TL_LIMITED_EMAIL_PILOT_PROCESSING_ENABLED=false
TL_NOTIFICATION_DELIVERY_ENABLED=false
TL_NOTIFICATION_WORKER_ENABLED=false
```

Then pause the run from `/admin/email-pilot.php`.

For a provider or webhook incident, also disable:

```bash
TL_NOTIFICATION_TEST_DELIVERY_ENABLED=false
TL_RESEND_WEBHOOK_ENABLED=false
```

## Validation

```bash
bash ./run-full-syntax-check.sh
bash ./run-quality-gate.sh
php ./bin/product-acceptance.php
php ./bin/limited-email-pilot-check.php --json
```

CI validates contracts and pure health-threshold behavior without sending production email or receiving a live webhook.

## Rollback

1. Set `TL_LIMITED_EMAIL_PILOT_PROCESSING_ENABLED=false`.
2. Set `TL_NOTIFICATION_DELIVERY_ENABLED=false`.
3. Confirm `TL_NOTIFICATION_WORKER_ENABLED=false`.
4. Pause or reject the open pilot.
5. Disable the Resend webhook only when webhook ingestion itself is part of the incident.
6. Restore the previous application package while preserving `/labs/config.php`.
7. Retain pilot runs, members, checks, events, outbox, attempts, provider events/states, preferences, and suppressions for audit.
8. Rotate provider or webhook secrets only when exposure is suspected.

Do not drop the additive Section 18 tables during an operational rollback.
