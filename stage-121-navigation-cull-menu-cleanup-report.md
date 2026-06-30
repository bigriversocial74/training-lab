# Stage 121 Navigation Cull + Menu Cleanup Report

## Purpose

This build stops the page-catalog expansion and returns the standalone Training Lab to a focused product structure.

## What changed

- Culled active app sidebar navigation to the core Training Lab workflow pages.
- Culled active admin sidebar navigation to the core backend/operations pages.
- Simplified the top public/main menu.
- Rebuilt `app/index.php` as a focused app dashboard.
- Rebuilt `admin/index.php` as a focused admin overview.
- Rebuilt `admin/command-center.php` as the primary admin operations screen.
- Simplified `api/training/ops-overview.php` so it reports the focused Stage 121 navigation state.
- Added sidebar CSS fixes for grouped navigation, sticky desktop sidebars, and cleaner mobile menus.

## Active app sidebar pages

- `app/action-result.php`
- `app/campaign-builder.php`
- `app/campaign-detail.php`
- `app/campaigns.php`
- `app/challenge-library.php`
- `app/check-in.php`
- `app/coach-dashboard.php`
- `app/flow-board.php`
- `app/index.php`
- `app/launchpad.php`
- `app/message-board.php`
- `app/participant-portal.php`
- `app/progress-map.php`
- `app/proof-upload.php`
- `app/reflection-journal.php`
- `app/resource-hub.php`
- `app/rewards.php`
- `app/secure-dashboard.php`
- `app/sequence-tasks.php`
- `app/task-runner.php`
- `app/wallet.php`
- `app/workspace.php`

## Active admin sidebar pages

- `admin/action-result.php`
- `admin/auth-gate.php`
- `admin/campaign-inspector.php`
- `admin/campaigns.php`
- `admin/cohort-manager.php`
- `admin/command-center.php`
- `admin/db-health.php`
- `admin/event-timeline.php`
- `admin/flow-control.php`
- `admin/index.php`
- `admin/participant-inspector.php`
- `admin/qa-center.php`
- `admin/reporting-center.php`
- `admin/review-inspector.php`
- `admin/review-queue.php`
- `admin/review-workbench.php`
- `admin/reward-inspector.php`
- `admin/route-check.php`
- `admin/scenario-runner.php`
- `admin/secure-dashboard.php`
- `admin/stage7-control.php`
- `admin/task-inspector.php`

## Removed from active app navigation

- `app/assessment-center.php`
- `app/automation-planner.php`
- `app/badge-studio.php`
- `app/cohort-calendar.php`
- `app/cohort-scoreboard.php`
- `app/daily-agenda.php`
- `app/decision-journal.php`
- `app/enrollment-wizard.php`
- `app/escalation-matrix.php`
- `app/evidence-locker.php`
- `app/evidence-review-room.php`
- `app/facilitator-briefing.php`
- `app/feedback-inbox.php`
- `app/focus-timer.php`
- `app/goal-planner.php`
- `app/guided-onboarding.php`
- `app/habit-builder.php`
- `app/implementation-roadmap-app.php`
- `app/knowledge-checks.php`
- `app/learning-contract.php`
- `app/learning-path.php`
- `app/live-demo-script.php`
- `app/mentor-notes.php`
- `app/milestone-tracker.php`
- `app/operator-console.php`
- `app/outcome-dashboard.php`
- `app/outcome-review.php`
- `app/participant-directory.php`
- `app/peer-review-room.php`
- `app/practice-lab.php`
- `app/practice-queue.php`
- `app/prompt-lab.php`
- `app/readiness-checklist.php`
- `app/reminder-center.php`
- `app/resource-checklist.php`
- `app/role-simulator.php`
- `app/scenario-debrief.php`
- `app/skill-matrix.php`
- `app/sprint-board.php`
- `app/success-plan.php`
- `app/system-tour.php`
- `app/team-pulse.php`
- `app/training-marketplace.php`
- `app/training-retrospective.php`

## Removed from active admin navigation

- `admin/admin-intake-desk.php`
- `admin/audit-trail-plus.php`
- `admin/backup-planner.php`
- `admin/build-review.php`
- `admin/certificate-center.php`
- `admin/certificate-verify.php`
- `admin/content-calendar.php`
- `admin/content-studio.php`
- `admin/data-explorer.php`
- `admin/data-stewardship.php`
- `admin/demo-narrative.php`
- `admin/demo-ops.php`
- `admin/export-preview.php`
- `admin/final-review-console.php`
- `admin/insight-console.php`
- `admin/integration-sandbox.php`
- `admin/intervention-center.php`
- `admin/launch-readiness.php`
- `admin/master-control-room.php`
- `admin/metrics-center.php`
- `admin/operator-playbook.php`
- `admin/partner-enablement.php`
- `admin/persona-builder.php`
- `admin/program-rules.php`
- `admin/quality-gates.php`
- `admin/release-board.php`
- `admin/review-analytics.php`
- `admin/risk-register.php`
- `admin/rollout-planner.php`
- `admin/rubric-builder.php`
- `admin/safety-center.php`
- `admin/simulator-controls.php`
- `admin/sop-checklist.php`
- `admin/support-desk.php`
- `admin/workflow-composer.php`

## Images/assets preserved

Total image files preserved in the package: **29**

- `assets/img/admin/backend-overview.svg`
- `assets/img/app/participant-dashboard.svg`
- `assets/img/icons/calendar.png`
- `assets/img/icons/check-list.png`
- `assets/img/icons/flame.png`
- `assets/img/icons/flask.png`
- `assets/img/icons/gift.png`
- `assets/img/icons/growth.png`
- `assets/img/icons/heart.png`
- `assets/img/icons/sprout.png`
- `assets/img/icons/upload.png`
- `assets/img/icons/verified.png`
- `assets/img/marketing/about-progress.svg`
- `assets/img/marketing/about-team.png`
- `assets/img/marketing/auth-guy.png`
- `assets/img/marketing/blog-article.png`
- `assets/img/marketing/blog-landing.png`
- `assets/img/marketing/cart-visual.png`
- `assets/img/marketing/checkout-visual.png`
- `assets/img/marketing/contact-visual.png`
- `assets/img/marketing/hero-task-reward.png`
- `assets/img/marketing/how-it-works-process.png`
- `assets/img/marketing/pricing-growth.png`
- `assets/img/marketing/receipt-visual.png`
- `assets/img/marketing/signin-visual.png`
- `assets/img/marketing/signup-visual.png`
- `assets/img/marketing/success-visual.png`
- `assets/img/marketing/team-page.png`
- `assets/img/marketing/training-lab-hero.svg`

## Safety

- No config files moved or overwritten.
- No auth gates added.
- No new SQL required.
- No image or asset files removed.
- No wallet/payment/reward/claim/redeem production logic added.
- Prototype route files were left in the package for backward compatibility, but they are no longer promoted in sidebars/main menus.
