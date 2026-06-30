# Training Lab Stage 2 Template Package

Public-facing pages have been restyled to match the approved mocked Training Lab landing template.

## Local preview

```bash
php -S 127.0.0.1:8091 -t labs
```

Open:

```text
http://127.0.0.1:8091/
```

## Updated public pages

```text
labs/index.php
labs/how-it-works.php
labs/pricing.php
labs/about.php
labs/team.php
labs/blog.php
labs/blog-article.php
labs/contact.php
labs/signup.php
labs/signin.php
labs/cart.php
labs/checkout.php
labs/success.php
labs/receipt.php
```

## Shared public style

```text
labs/assets/css/public-template.css
```

## Stage 2 boundary

```text
visual/browser-only shell
no database writes
no real uploads
no payments
no wallet balance changes
no claim/redeem logic
no separate account system
no production hosting changes
```


## Mobile header cleanup

```text
Main public navigation uses a hamburger slide-out on mobile.
Logo lockup is text-only: Training Lab by Microgifter. No adjacent logo icon and no extra tagline under the logo.
Public pages include /assets/js/public-template.js for nav open/close behavior.
```

## Stage 3/4 Checkpoint

Stage 3 and Stage 4 are now built as a read-only scaffold:

- Shared service: `includes/training-lab-stage34-service.php`
- API contract endpoints: `api/training/*.php`
- App/admin pages consume centralized service data
- Demo actions still use browser-only `localStorage`
- No SQL has been created yet
- SQL should be delivered later as one consolidated SQL file after review

Stage 3/4 report: `stage-3-4-build-report.md`

## Stage 5 Pre-SQL Integration Prep

Stage 5 adds a read-only adapter shell and database mapping checklist while keeping the build inside the safe boundary.

New files:

```text
labs/includes/training-lab-stage5-adapters.php
labs/api/training/readiness.php
labs/stage-5-pre-sql-build-report.md
labs/stage-5-single-sql-boundary.md
```

New readiness endpoint:

```text
/api/training/readiness.php
```

SQL policy:

```text
When database work begins, create one consolidated SQL file only after the existing schema is uploaded and reviewed.
```

Still not included:

```text
No SQL
No database connection
No database writes
No real uploads
No payments
No wallet balance changes
No reward issuing
No claim/redeem logic
No separate account system
No production hosting/DNS changes
```

## Stage 7 controlled backend build

After importing `labs/database/training_lab_stage6_consolidated_import_safe.sql`, copy:

```bash
cp labs/config/training-lab-db.sample.php labs/config/training-lab-db.local.php
```

Then fill in the database credentials.

Stage 7 endpoints:

```text
/api/training/db-status.php
/api/training/actions/seed-demo.php
/api/training/actions/create-campaign.php
/api/training/actions/join-campaign.php
/api/training/actions/submit-proof.php
/api/training/actions/review-proof.php
/api/training/actions/evaluate-rewards.php
```

Admin control page:

```text
/admin/stage7-control.php
```

Stage 7 writes only to Training Lab tables. It does not process real uploads, issue rewards, change wallet balances, process payments, or create claim/redeem behavior.
