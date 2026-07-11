# Merchant Campaign + Task Builder Completion v1

## Purpose

Section 19 replaces the read-only campaign preview with one owner-scoped merchant builder for the complete Training Lab campaign lifecycle.

## Included

- Create private campaign drafts
- Edit campaign title, summary, description, type, audience, capacity, timezone, schedule, enrollment mode, participant instructions, status, and visibility
- Duplicate any owned campaign into a private draft
- Archive campaigns without deleting participant history
- Create, edit, archive/delete, and reorder tasks
- Task duration, due date, prerequisite, overdue-close, status, and proof requirements
- Proof instructions required for proof-enabled tasks
- Reward-rule creation or copy/attachment from the same merchant workspace
- Participant preview
- Publish-readiness score and fail-closed publication
- Manager ownership scope with administrator platform scope
- Post/Redirect/Get actions protected by method, origin, CSRF, rate, and trusted-role controls

## Authority boundaries

The builder writes only these Training Lab records:

- `training_campaigns`
- `training_campaign_tasks`
- `training_reward_rules`
- `training_events`

It does not upload files, send email, enable workers, issue a Microgift, call Microgifter, change wallet balances, process payments, claim or redeem rewards, or delete campaigns and participant history.

Tasks with proof history are archived rather than deleted.

## SQL

**No new SQL required.**

Section 19 uses the existing Training Lab campaign, task, reward-rule, proof, and event schema. Advanced builder settings are retained in the existing `settings_json` fields, with schedule and capacity columns updated when they are present in the deployed schema.

## Deployment

1. Back up Training Lab files and the database.
2. Preserve the private `/labs/config.php`.
3. Deploy the current `main` package.
4. Run:

```bash
bash ./run-full-syntax-check.sh
bash ./run-quality-gate.sh
php ./bin/product-acceptance.php
```

5. Sign in through the shared Microgifter handoff as a merchant manager.
6. Open `/admin/campaigns.php` or `/admin/campaign-builder.php`.
7. Create a private draft, add tasks, attach a reward rule, and inspect the participant preview.
8. Confirm publishing is blocked until every readiness check passes.
9. Publish one controlled campaign and test enrollment through a participant account.

## Rollback

1. Stop merchant editing during rollback.
2. Restore the previous application package while preserving `/labs/config.php`.
3. Keep campaign, task, reward-rule, proof, participant, and audit records for history.
4. Do not destructively reverse schema or delete campaigns created through the builder.
5. Archive any test campaign that should no longer be visible.

Rollback requires no SQL reversal.
