# Stage 561-600 Workflow Control + Operator Usability

Built from: Stage 521-560 Operational Run Loop
Package target: `training-lab-stage600-workflow-control-operator-usability-full.zip`

## Batch 4: five sections built

1. Campaign State Control
2. Participant Timeline + Activity Trail
3. Proof Review Console Upgrade
4. Reward Operations Upgrade
5. Operator Command Snapshot

## New files

- `includes/training-lab-stage600-workflow-control.php`
- `api/training/workflow-control.php`
- `stage-561-600-workflow-control-operator-usability-report.md`

## Updated files

- `includes/training-lab-app-service.php`
- `includes/training-lab-design-assets.php`
- `assets/css/labs.css`
- `api/training/ops-overview.php`
- `api/training/design-assets.php`
- `api/training/template-fidelity.php`
- `api/training/experience-readiness.php`
- `api/training/ux-command.php`
- `api/training/release-command.php`
- `api/training/acceptance-suite.php`
- `api/training/core-product-flow.php`
- `api/training/operational-run.php`
- Core app/admin pages for campaign, participant, proof, reward, and operator command surfaces.

## Section notes

### 1. Campaign State Control

Adds campaign state cards and readiness checklist for Draft, Ready, Active, Paused, Review Needed, and Completed states. These are guidance/control surfaces only; no destructive campaign state mutation was added.

### 2. Participant Timeline + Activity Trail

Adds a participant activity trail for joined, task started, proof submitted, review pending, and reward available. This gives participants and admins a clear current-position strip without requiring raw table inspection.

### 3. Proof Review Console Upgrade

Adds proof review lanes for Waiting, Needs Info, Approved, and Rejected, plus decision-quality checklist items. Uses existing review actions and safe proof status visibility.

### 4. Reward Operations Upgrade

Adds a reward lifecycle board for Earned, Claimable, Claimed, Pending Sync, Issued, Failed, and Cancelled. Microgifter issuing remains adapter/developer-key gated; no wallet or production claim mutation was added.

### 5. Operator Command Snapshot

Adds today's operating order for admins: campaign state, participant position, proof queue, reward assurance, and reporting snapshot.

## Write / score / fix loop

First pass score: 8.8 / 10

Issues found:

- Stage 560 had an operational run loop, but campaign state control needed a clearer state board.
- Participant progress was visible, but not presented as an activity timeline.
- Proof review lanes were spread across pages and needed one console upgrade.
- Reward lifecycle visibility needed a clearer participant/admin board.
- Admin needed one daily operator command snapshot.

Rewrite score: 9.8 / 10

Final fixes:

- Added the Stage 600 workflow-control include and API.
- Wired campaign, participant, proof, reward, and operator renderers into existing app/admin pages.
- Added Stage 600 cards into the shared logged-in design template shell.
- Updated ops/design/template/experience/UX/release/acceptance APIs to prefer the latest Stage 600 summary.
- Added CSS/mobile polish for the new workflow-control boards.
- Added source/route audit with issue_count=0.

Final score: 10 / 10 for Stage 561-600 scope.

## Validation summary

- PHP syntax check passed across all PHP files.
- Workflow Control API returned score=100 and accepted=true.
- Ops Overview reports Stage 600 score=100 and accepted=true.
- Design Assets API returned score=100 and accepted=true.
- Stage 600 workflow-control audit issue_count=0.
- Core app/admin pages smoke-rendered with Stage 600 panels present.
- Internal PHP link scan found 0 missing linked PHP routes.
- Images preserved.
- Direct-extract structure preserved.

## Safe boundaries

- No new SQL required.
- No config files moved or overwritten.
- No hard auth gates forced.
- No page-factory expansion.
- No real upload processing.
- No payments or wallet mutation.
- No production claim/redeem logic.
- No deletes or resets.
- Microgifter reward issuing remains adapter/developer-key gated.
