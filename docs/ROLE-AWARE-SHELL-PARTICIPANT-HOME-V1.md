# Role-Aware App Shell + Participant Home v1

## Product outcome

This section replaces the Training Lab's diagnostic-style entry experience with a role-aware product shell and a signed-in participant home.

## Included

- Central page-access enforcement for `/app/` and `/admin/` pages.
- Participant, reviewer, manager, and administrator navigation profiles.
- Participant identity derived from the trusted session instead of a browser-supplied user ID.
- A new participant home with current campaign, progress, tasks, review state, rewards, recent activity, and one recommended next action.
- A role-aware management home for reviewers and merchant managers.
- Merchant-owned dashboard aggregates for campaigns, participants, proof reviews, and rewards.
- Consolidation of the older Launchpad and Mission Control pages into `/app/index.php`.
- Shared responsive components for product dashboards, task rows, status states, activity, quick links, and management lists.
- Dedicated contract and scored-audit checks in the PHP 8.2/8.3 quality gate.

## Access model

- Participant pages require an authenticated participant or higher role.
- Review pages allow trusted coach/reviewer roles and higher.
- Other management pages require a trusted manager or administrator.
- System-health navigation appears only for administrators.
- Unauthorized users are redirected to the safest permitted landing page.

The access model reuses the existing trusted Microgifter role context. It does not create a second role or permission authority.

## Participant identity boundary

The participant home uses `tl_security_numeric_user_id()` with the trusted current user. Campaign enrollment reads are prepared and scoped by `training_participants.user_id`. Legacy `user_id` query parameters are ignored by the consolidated participant entry routes.

## Merchant tenant boundary

Merchant managers see only campaigns where `training_campaigns.owner_user_id` matches their trusted account. Participant, proof-review, and reward counts are joined through those owned campaign records. Manager dashboard queries use prepared statements and do not accept an owner identity from the browser.

Administrators retain platform-wide visibility. Reviewers receive review-focused visibility without merchant campaign-management or reward-operation totals.

## Database and integration boundary

No SQL is required.

This section reuses:

- `training_campaigns`
- `training_campaign_tasks`
- `training_participants`
- `training_proof_submissions`
- `training_reviews`
- `training_action_receipts`
- `training_reward_events`
- `training_streaks`

No Microgifter authentication, payment, wallet, claim, redemption, gift, or reward-authority table is written by this section. Existing reward adapters and rollout gates are unchanged.

## Validation

Run:

```bash
bash ./run-quality-gate.sh
```

The section is acceptance-ready only when:

- Recursive PHP syntax passes.
- The role-aware shell contract passes.
- Every scored section reports 10/10.
- Merchant ownership isolation checks pass.
- The complete existing quality gate passes on PHP 8.2 and PHP 8.3.

## Rollback

Revert the feature PR. No database rollback or private configuration change is required.
