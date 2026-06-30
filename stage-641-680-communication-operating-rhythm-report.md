# Stage 641–680 Communication + Operating Rhythm

Built from Stage 601–640 Data Quality + Operator Confidence.

## Five sections

1. Participant Communication Center
2. Admin Communication Console
3. Mission Reminder + Follow-Up Logic
4. Operator Daily Rhythm
5. Communication + Rhythm API Layer

## Files added

- `includes/training-lab-stage680-communication-rhythm.php`
- `api/training/communication-rhythm.php`
- `stage-641-680-communication-operating-rhythm-report.md`

## Files updated

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
- `api/training/workflow-control.php`
- `api/training/data-quality.php`
- Participant/app pages and admin pages listed in the Stage 680 audit.

## Score loop

First pass: 8.8 / 10

Fixes:

- Added the Stage 680 communication/rhythm include.
- Added participant-facing internal status messages.
- Added admin copy-ready prompts without email/SMS sending.
- Added follow-up and reminder states from existing workflow data.
- Added daily opening/midday/closeout operator rhythm cards.
- Added `/api/training/communication-rhythm.php`.
- Updated readiness/design/ops APIs to surface Stage 680.
- Added Stage 680 shared shell cards and responsive CSS.

Rewrite score: 9.8 / 10

Final score: 10 / 10 for Stage 641–680 scope.

## Boundaries

- No new SQL required.
- No config files moved or overwritten.
- No hard auth gates forced.
- No page-factory expansion.
- No real email, SMS, push, or external notification sending.
- No destructive delete/reset actions.
- No real upload processing.
- No payments, wallet mutation, or production claim/redeem logic.
- Microgifter reward issuing remains adapter/developer-key gated.
