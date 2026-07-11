# Onboarding + Guided Empty States v1

## Outcome

Training Lab now provides two role-aware setup paths:

- Participant: account → campaign → first task → verification → reward progress.
- Merchant manager: campaign → tasks → reward rule → publish → participants.

Each checklist is computed from trusted database records and the signed-in role. It does not use browser-selected user IDs, fixture campaigns, local-storage completion flags, or demo progress.

## Empty-state standard

A guided empty state must explain:

1. what is missing;
2. why the next step matters;
3. one primary action into the current product workflow.

The shared `tl_onboarding_empty_state()` component provides this structure without creating data or bypassing access controls.

## Merchant isolation

Manager readiness queries are scoped through `training_campaigns.owner_user_id`. Administrators may inspect platform-wide readiness. A merchant cannot see another merchant’s campaign, task, reward-rule, publishing, or enrollment totals.

## Authority boundary

Onboarding is read-only guidance. It does not create a second account, role, campaign authority, participant identity, reward, wallet, payment, claim, redemption, gift, or Microgifter integration authority.

## SQL

No SQL is required.
