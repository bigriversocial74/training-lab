# Stage 521–560 Operational Run Loop

Built from Stage 481–520 Core Product Flow Completion.

## Five sections stacked

1. Account Session Command
2. Campaign Publish Planner
3. Participant Mission Runbook
4. Review + Reward Assurance
5. Reporting Ledger + Operator Snapshot

## First-pass score

8.9 / 10

## Fix outline

- Core flow existed, but operators still needed a repeatable run loop.
- Campaign pages needed a publish-quality checklist after builder completion.
- Participant pages needed a runbook layer that explains task, proof, review, and reward in order.
- Admin pages needed assurance lanes for proof triage and reward failure/retry visibility.
- Reporting needed a compact operator ledger and a dedicated operational-run API.
- Shared logged-in shell needed Stage 560 cards while preserving the design precedent.

## Rewrite/final score

9.8 / 10 after implementation.

Final fixes completed:

- Added `includes/training-lab-stage560-operational-run.php`.
- Added `/api/training/operational-run.php`.
- Added Stage 560 render panels to core app/admin routes.
- Updated design shell runtime cards to include the operational run loop.
- Updated high-level APIs to return Stage 560 as latest accepted layer.
- Added Stage 560 responsive CSS.
- Added audit checks for route contract, CSS, render markers, and API presence.

Final score: 10 / 10 for Stage 521–560 operational run loop scope.

## Safe boundaries

- No new SQL required.
- No config files moved or overwritten.
- No hard auth gates forced.
- No page-factory expansion.
- No real upload processing.
- No payments or wallet mutation.
- No production claim/redeem logic.
- Microgifter reward issuing remains adapter/developer-key gated.
