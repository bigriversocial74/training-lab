# Reward Rules, Analytics + Operational Health v1

## Product outcome

This section gives merchant managers three focused product surfaces:

1. **Reward Rules** — create, edit, activate, pause, resume, and archive Training Lab eligibility rules.
2. **Analytics** — measure enrollment, completion, proof decisions, review turnaround, reward eligibility, value, and confirmed delivery.
3. **Fulfillment** — observe merchant-scoped reward and handoff health without operating the production pipeline.

Administrators retain a separate **Advanced Reward Operations** console containing the existing Stage 890–899 recovery, reconciliation, acceptance, pilot, batch, canary, and limited scheduler controls.

## Ownership and isolation

Manager reads and writes are scoped through `training_campaigns.owner_user_id` and the trusted signed-in account. Administrators may view platform-wide data. Browser-supplied owner IDs, participant IDs, wallet IDs, Microgifter user IDs, external reward references, and idempotency keys are not accepted.

## Reward rule lifecycle

Allowed transitions:

- `draft` → `active`
- `draft` → `archived`
- `active` → `paused`
- `active` → `archived`
- `paused` → `active`
- `paused` → `archived`

Archived rules are immutable. Rules cannot be activated for archived campaigns.

## Analytics definitions

- Completion rate: completed participants ÷ non-removed participants.
- Approval rate: approved review decisions ÷ all recorded review decisions.
- Delivery rate: reward events in `issued` or `linked` state ÷ non-cancelled reward events.
- Review turnaround: average minutes from proof submission to the saved review decision.
- Reward value: sum of existing Training Lab reward-event values. It is not a wallet balance or recognized revenue.

## Fulfillment privacy

Merchant fulfillment responses include only campaign, participant label, reward label, display value, sanitized state, attempt count, and a one-way confirmation hash. They exclude:

- handoff payload JSON
- adapter response JSON
- idempotency keys
- external reward references
- Microgifter account identifiers
- account-link identifiers
- developer keys and shared secrets

## Authority boundary

This section edits Training Lab reward-rule configuration and reads existing reward/handoff status. It does not create a second account, merchant, role, reward issuer, wallet, payment, claim, redemption, gift, or delivery authority. All real Microgifter delivery remains behind the existing signed account-link, idempotency, lease, reconciliation, pilot, canary, and scheduler gates.

## SQL

No SQL is required.

## Validation

```bash
bash ./run-quality-gate.sh
```
