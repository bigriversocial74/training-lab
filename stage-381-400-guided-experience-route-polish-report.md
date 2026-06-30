# Stage 381–400 Guided Experience and Route Polish

Built from Stage 380 logged-in design system hardening.

## Goal

Keep the logged-in app/admin visual precedent from the mockups, but make the shell more useful as a real application surface. This pass adds guided action decks, runtime progress binding, route priority awareness, and a focused experience-readiness QA gate without expanding the page factory.

## Builds stacked

1. Build 39: Guided Action Decks
2. Build 40: Dynamic Progress Binding
3. Build 41: App/Admin Route Priority Map
4. Build 42: Experience Readiness API
5. Build 43: Backend Readiness Gate + Mobile Polish

## New file

- `api/training/experience-readiness.php`
- `stage-381-400-guided-experience-route-polish-report.md`

## Updated files

- `includes/training-lab-design-assets.php`
- `assets/css/labs.css`
- `api/training/template-fidelity.php`
- `api/training/design-assets.php`
- `api/training/ops-overview.php`
- `admin/backend-readiness.php`

## First-pass score: 8.8/10

Issues found after the first pass:

- Stage 380 matched the logged-in/app admin visual precedent, but the hero shell still did not guide users strongly enough into the next task/proof/reward/admin action.
- Progress bars were visually correct but needed explicit runtime width binding.
- Backend Readiness did not have a focused Stage 400 gate.
- APIs exposed Stage 380 readiness, but not the new guided-experience checks.
- Mobile density needed another pass for the action deck.

## Fix outline

- Add `tl_stage400_context_runtime_overrides()` and make the logged-in renderer prefer it over Stage 380.
- Add a reusable guided action deck inside the logged-in template shell.
- Bind the progress indicator width to workflow/admin state.
- Add a route priority map for app, admin, and API routes.
- Add `/api/training/experience-readiness.php`.
- Update Template Fidelity, Design Assets, Ops Overview, and Backend Readiness to surface Stage 400.
- Add CSS for the guided action deck, readiness grid, route stack, and mobile layout.

## Rewrite score: 9.8/10

Remaining polish:

- Confirm the new API reports 100/100.
- Confirm all priority routes exist.
- Confirm logged-in template audit still checks 43 app/admin pages.
- Confirm no missing internal PHP links.

## Final score: 10/10

Accepted for the Stage 381–400 scope.

## Validation

- PHP syntax check passed across all PHP files.
- Smoke-rendered public/auth/app/admin/API routes.
- Experience Readiness API returned `accepted=true`, `score=100`.
- Template Fidelity API returned `accepted=true`, `score=100`.
- Design Assets API returned `accepted=true`, `score=100`.
- Logged-in template audit checked 43 contexts with 0 issues.
- Internal core PHP link scan found 0 missing linked PHP routes.
- Images preserved: 29 image files.
- Package contains 219 real files.
- No wrapper folder.
- Direct-extract structure preserved.

## Boundaries

- No new SQL required.
- No config files moved or overwritten.
- No hard auth gates forced.
- No page-factory expansion.
- No real upload processing.
- No payments.
- No wallet mutation.
- No production claim/redeem logic.
- No deletes/resets/external notifications added.
