# Campaign Discovery, Detail + Enrollment v1

## Product outcome

This section replaces fixture-driven campaign pages with a signed-in, database-backed participant experience.

## Included

- Available, My Campaigns, and Completed views.
- Participant-scoped search across campaign title, description, type, and audience.
- Published-or-authorized campaign visibility.
- Campaign detail with tasks, duration, proof requirements, reward summary, dates, participation, and requirements.
- Product states for open, upcoming, invited, in progress, paused, ended, completed, full, closed, and unavailable campaigns.
- Protected enrollment and invitation acceptance.
- One transaction-safe enrollment service used by the HTML route, specific join API, generic app-action API, and legacy app action result.
- Responsive campaign cards, filters, search, detail layout, and mobile enrollment actions.

## Trusted identity and visibility

Participant identity comes from the trusted Training Lab session through `tl_security_numeric_user_id()`. Browser-supplied user IDs are not accepted by the campaign catalog, detail, or enrollment routes.

A campaign is visible when one of these conditions is true:

- It is published.
- The signed-in user already has a participant or invitation record.
- The signed-in user owns the campaign and is previewing it through the product shell.

Private campaigns are not exposed to unrelated participants.

## Enrollment rules

The secure enrollment service:

- Locks the campaign row before eligibility and capacity checks.
- Locks an existing participant row before deciding whether to reuse or activate it.
- Reuses existing enrollment idempotently.
- Accepts active invitations without creating a duplicate participant.
- Rejects removed access.
- Rejects expired invitations.
- Requires new self-enrollment campaigns to be published and scheduled or active.
- Rejects campaigns after their configured end date using the campaign timezone.
- Enforces participant capacity inside the transaction.
- Creates the participant and streak records together.
- Records an audit event.
- Rolls back on any failure.

## Authority boundaries

This section reuses the existing Training Lab campaign, participant, task, proof, reward-rule, reward-event, streak, and event tables. It does not create a second account, merchant, role, reward, wallet, payment, claim, redemption, or gift authority.

Reward delivery remains controlled by the existing Microgifter reward bridge and rollout gates.

## SQL

No SQL is required.

Campaign capacity, audience, and requirements use existing `training_campaigns.settings_json` values when configured. Existing schema columns provide campaign visibility, status, dates, timezone, tasks, participants, and rewards.

## Validation

Run:

```bash
bash ./run-quality-gate.sh
```

Acceptance requires:

- Recursive PHP syntax.
- Campaign discovery/detail/enrollment contract.
- 10/10 in every campaign-experience audit category.
- Existing security, data-integrity, route, account, reward, worker, and reconciliation contracts.
- PHP 8.2 and PHP 8.3 GitHub Actions success.

## Rollback

Revert the feature PR. No database rollback or private configuration change is required.
