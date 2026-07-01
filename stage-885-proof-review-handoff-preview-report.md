# Stage 885 Proof Review + Award Handoff Preview

Built from Stage 884 Real Microgifter Read Adapter Connection.

## Goal

Turn the live Stage 884 read adapter data into the first real Training Lab operating workflow:

```text
submitted proof -> review decision -> Training Lab receipt/eligibility record -> award handoff preview
```

## Added

- `includes/training-lab-stage885-proof-review-handoff.php`
- `api/training/proof-review-workflow.php`
- Stage 885 workflow rendering on `admin/review-workbench.php`
- Stage 885 review decision routing through `admin/action-result.php`
- Stage 885 documentation in `README.md`

## Workflow

Stage 885 reads the live proof queue from the existing Training Lab database, then allows an admin to submit one of these decisions:

```text
approved
needs_more_info
rejected
```

Approved decisions use the existing guarded Training Lab review action to write only Training Lab records:

```text
training_reviews
training_proof_submissions
training_action_receipts
training_reward_events
training_events
training_streaks
```

## Handoff preview

After a decision, Stage 885 produces a Microgifter handoff preview showing:

- selected proof
- latest review
- related receipts
- reward eligibility events
- preview-only target system information

The handoff explicitly reports:

```text
would_issue_microgifter_reward: false
would_create_claim: false
would_mutate_wallet: false
handoff_mode: preview_only
```

## API

Read current workflow status:

```text
GET /api/training/proof-review-workflow.php
```

Review a proof:

```text
POST /api/training/proof-review-workflow.php
proof_id=<proof id or public id>
decision=approved|needs_more_info|rejected
review_notes=<notes>
```

## Admin

Human workflow page:

```text
/admin/review-workbench.php
```

## Safety boundaries

- No new SQL migrations.
- No config files moved or overwritten.
- No hard auth gates forced.
- No real upload processing.
- No payment processing.
- No wallet balance mutation.
- No production claim/redeem mutation.
- No destructive Microgifter sync.
- No Microgifter reward issuing.
- Training Lab review writes only.
- Award handoff remains preview-only.
