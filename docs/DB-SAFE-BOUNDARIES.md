# Training Lab DB Safe Boundaries

This project is a training/simulation lab. Database work must preserve the safe lab boundary.

## Required defaults

Training Lab behavior should remain safe by default:

- Proof records are metadata/demo records only.
- File upload flows must not store real user uploads unless explicitly approved.
- Reward events are training events only.
- Account balances must not be changed by lab actions.
- Payment flows must remain disabled unless explicitly approved.
- Claim/redeem flows must remain disabled or simulated unless explicitly approved.

## SQL rules

When adding or editing SQL:

1. Prefer import-safe scripts for shared hosting / phpMyAdmin compatibility.
2. Avoid external foreign keys unless the referenced tables are guaranteed to exist in this repo's database setup.
3. Avoid CHECK constraints if compatibility is uncertain.
4. Do not insert into production role or permission tables from a lab import unless specifically approved.
5. Name SQL files clearly:
   - `*_import_safe.sql` for scripts intended to run.
   - `*_archive.sql` or `*_do_not_run.sql` for historical scripts.
6. Update this doc or the PR body with the exact SQL file that should be run.

## Canonical SQL note

If both an import-safe SQL file and an older consolidated SQL file exist, treat the import-safe file as the default candidate and treat the older consolidated file as archive/do-not-run until manually reviewed.

## PR review checklist

Before merging DB changes, confirm:

- The PR states whether SQL is required.
- The PR names the exact SQL file to run.
- The SQL avoids risky external dependencies.
- The SQL does not enable real uploads, payments, account-balance changes, or live claim redemption by default.
