# Task Detail, Status + Proof Revisions v1

## Product outcome

This section replaces the multi-task diagnostic runner with one signed-in participant task experience.

## Included

- One selected task at a time with campaign context, instructions, duration, due date, proof requirement, and campaign progress.
- Ordered task prerequisites using the existing task position sequence.
- Product states for ready, locked, starts soon, paused, overdue, past due, in review, needs an update, complete, and campaign ended.
- Text and HTTP(S) link proof submission.
- Checklist completion with an immediate verified Training Lab receipt.
- Reviewer feedback and participant proof revision history.
- Previous, next, task-path, campaign-detail, and progress navigation.
- Consolidation of the older proof-upload route into the task detail experience.
- Responsive task, proof, review-history, and mobile action components.

## Trusted identity and ownership

The task read model and write service derive the participant from the trusted Training Lab session with `tl_security_numeric_user_id()`. Browser-supplied participant or user IDs are ignored.

A task can be viewed or submitted only when:

- The signed-in user has a non-removed participant record for the campaign.
- The task belongs to that campaign.
- The task is active.
- Campaign and participant state allow submission.
- Every earlier active task has a verified receipt.

## Secure task submission

The shared task submission service is used by:

- `/app/task-submit.php`
- `/api/training/actions/submit-proof.php`
- `/api/training/app-action.php` for `complete_task` and `submit_proof`
- `/app/action-result.php` for legacy task forms

The service:

- Starts one database transaction.
- Locks the campaign/participant enrollment and selected task.
- Checks earlier task completion before accepting the current task.
- Locks the latest proof and existing completion receipt.
- Returns idempotently when the task is already complete.
- Rejects duplicate submissions while proof is in review.
- Validates text length and HTTP(S) proof links.
- Creates checklist proof and completion receipt atomically.
- Updates streak progress for verified checklist completion.
- Evaluates rewards only after the task transaction commits.
- Rolls back on any failure.

## Proof revision lineage

No new proof-revision table is required.

Every new proof stores these values in the existing `training_proof_submissions.metadata_json`:

- `revision_number`
- `revision_of_public_id`
- `source`
- `trusted_actor`

When a participant responds to `needs_more_info` or a rejected proof:

- A new proof row is created.
- The previous proof is retained and marked `cancelled`.
- The previous proof metadata receives `replaced_by_public_id` and `replaced_at`.
- Existing reviewer decisions remain attached to the original proof.
- The participant timeline displays every submission and review decision.

## Upload boundary

This section supports text proof and optional HTTP(S) links only. It does not enable image, video, audio, or file upload processing. No file is uploaded, scanned, stored, or approved by this section.

## Authority boundaries

This section reuses existing Training Lab campaign, task, participant, proof, review, receipt, streak, reward, and audit-event records. It does not create a second account, role, merchant, reward, wallet, payment, claim, redemption, or gift authority.

Microgifter reward delivery remains behind the existing signed bridge, idempotent handoff, reconciliation, pilot, canary, and limited-scheduler gates.

## SQL

No SQL is required.

The existing task `settings_json` supports task-specific `due_at`, `deadline`, and `close_after_due` values. Proof revision lineage uses the existing proof metadata JSON.

## Validation

Run:

```bash
bash ./run-quality-gate.sh
```

Acceptance requires:

- Recursive PHP syntax.
- Task detail/status/proof revision contract.
- 10/10 in every Section 3 audit category.
- Existing role-shell, campaign, security, data-integrity, account, reward, worker, and reconciliation contracts.
- PHP 8.2 and PHP 8.3 GitHub Actions success.

## Rollback

Revert the feature PR. No database rollback or private configuration change is required.
