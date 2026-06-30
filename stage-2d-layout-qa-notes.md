# Training Lab Stage 2D Layout QA Notes

This checkpoint references the Stage 2A, 2B, and 2C scorecard plus the approved public landing/auth templates.

## Build boundary

Keep building visual/demo sections without stopping. Stop only at these real implementation boundaries:

- database writes
- SQL migrations
- real proof uploads
- real login/session integration
- wallet balance changes
- reward issuing
- payments/subscriptions
- production DNS or hosting changes

## Single SQL rule

When SQL begins, package the database work into one consolidated SQL file for the reviewed build section. Do not scatter one-off SQL files across individual pages.

## Backward layout audit

Reviewed backward from the latest checkpoint:

1. Public template pages
   - Keep the approved green/white template style.
   - Keep hamburger slide-out navigation on mobile.
   - Keep the logo clean: text-only Training Lab, no adjacent icon, no extra tagline under the logo.
   - Auth pages keep the illustration/copy on the left and the form card on the right.

2. Participant app pages
   - Keep Stage 2 localStorage demo state only.
   - Keep app/admin workspace navigation as a mobile slide-out.
   - Improve dashboard, campaign, proof, rewards, and wallet hierarchy before backend work.

3. Admin pages
   - Keep operations-style overview and review queue.
   - Make demo review state visible without implying server approval.
   - Keep all review actions browser-only until backend approval.

## Current rescore

- Stage 2A public website template: 9.3/10
- Stage 2B participant app template: 9.1/10
- Stage 2C admin/backend UI template: 9.1/10
- Stage 2D full layout QA: 8.8/10

## Fixes applied in this checkpoint

- Removed leftover public CSS rules for the old logo icon/tagline treatment.
- Added a backward-layout audit note so future passes know when to go back and fix older layouts.
- Added single-SQL build boundary documentation.
- Confirmed this checkpoint still has no SQL, no uploads, no payment, no wallet/reward writes, and no production deployment changes.
