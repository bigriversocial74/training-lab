# Training Lab Stage 3 Backend Integration Plan

Stage 3 is planning/specification only until explicitly approved for database work.

## Stop Boundary
Do not build or run real migrations yet. Do not wire uploads, payments, wallet writes, claim/redeem actions, or a separate account system.

## Existing Microgifter System Must Remain Source of Truth

Training Lab should attach to the existing Microgifter account system:

- users
- merchant / organization ownership
- roles and permissions
- wallet ownership
- reward ownership
- claim status
- billing/customer account data later

Training Lab should not create duplicate auth, duplicate wallets, duplicate billing, or duplicate reward ownership.

## Proposed Training Lab Tables

1. `training_campaigns`
   - campaign metadata
   - organization/merchant owner
   - visibility/status
   - start/end dates
   - reward policy reference

2. `training_campaign_tasks`
   - task title
   - task description
   - sequence order
   - proof requirement type
   - due window

3. `training_participants`
   - user/account reference
   - campaign reference
   - participant status
   - joined/completed timestamps

4. `training_proof_submissions`
   - participant reference
   - task reference
   - proof note
   - file/media reference later
   - submission status

5. `training_reviews`
   - proof submission reference
   - reviewer user reference
   - decision
   - reviewer note
   - reviewed timestamp

6. `training_action_receipts`
   - verified action receipt
   - participant reference
   - campaign/task reference
   - immutable receipt payload
   - created timestamp

7. `training_reward_rules`
   - campaign reference
   - rule type
   - threshold
   - reward reference later
   - active status

8. `training_reward_events`
   - participant reference
   - reward rule reference
   - wallet/reward link later
   - issue status
   - event timestamp

9. `training_streaks`
   - participant reference
   - current streak
   - best streak
   - last verified action date

10. `training_events`
   - append-only activity log
   - actor reference
   - entity type/id
   - event type
   - safe JSON payload

## API Contract Draft

- `GET /api/training/campaigns`
- `GET /api/training/campaigns/{id}`
- `GET /api/training/campaigns/{id}/tasks`
- `POST /api/training/proof-submissions` later, after upload policy approval
- `GET /api/training/review-queue`
- `POST /api/training/reviews/{id}/decision` later, after permissions approval
- `GET /api/training/wallet-preview`

## Permission Rules

Participant:
- view joined campaigns
- view own tasks
- submit proof later
- view own rewards/wallet preview

Organization/Admin:
- create/manage campaigns later
- view campaign participants
- review proof submissions
- view training analytics

System:
- create action receipts after approved reviews
- evaluate reward rules
- write reward events later

## Stage 3 Score

Initial planning score: 8/10

Fixes needed before database work:
- Confirm exact existing Microgifter user/account/merchant table names.
- Confirm existing wallet/reward tables and ownership rules.
- Confirm media storage policy.
- Confirm reviewer role permissions.

Rescore target after repository inspection: 10/10.
