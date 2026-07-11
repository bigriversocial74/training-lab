# Production Deployment + Live Acceptance v1

## Outcome

Training Lab now includes a repeatable, read-only production deployment and live acceptance workflow. It creates a verified release package, preserves the private configuration, checks the target runtime and database, validates HTTPS and security headers, and supports role-based browser-session smoke tests without exposing session cookies.

## 1. Back up the target

Before replacing files:

1. Back up the current Training Lab application files.
2. Back up the `ywzyeite_microlabs` database.
3. Save a private copy of the active `/labs/config.php`.
4. Record the currently deployed commit or package SHA-256.
5. Keep the existing reward-processing, pilot, canary, and scheduler settings unchanged.

## 2. Build the release package

From the accepted repository checkout:

```bash
php ./bin/build-release-package.php --release=<main-commit>
```

The builder creates a ZIP under `dist/` by default. The archive contains one outer `labs/` folder for the existing cPanel move-up workflow.

The builder excludes:

- `/config.php`;
- `/labs/config.php`;
- `.env` files;
- `.git`, GitHub workflow, generated package, private runtime, and storage folders;
- symbolic links;
- existing ZIP, TAR, GZ, and 7z archive artifacts.

The active private configuration is never included or replaced. Excluding previous archives prevents old deployments, backups, or release packages from being embedded inside the new production package.

## 3. Verify the package

```bash
php ./bin/verify-release-package.php --file=./dist/training-lab-<main-commit>.zip
```

Verification fails when:

- an entry escapes the outer `labs/` folder;
- a private config or `.env` file is present;
- a nested ZIP, TAR, GZ, or 7z archive artifact is present;
- a required application route or release tool is missing;
- the manifest is invalid;
- a packaged file does not match its SHA-256 hash;
- the manifest does not preserve the private config and reward-delivery boundaries.

## 4. Deploy through the established cPanel workflow

1. Upload the verified ZIP.
2. Extract it; the package creates an outer `/labs/` directory.
3. Move the contents of the extracted outer `/labs/` directory into the live Training Lab web root.
4. Do **not** overwrite the existing `/labs/config.php`.
5. Confirm `/labs/config-example.php` is present for reference only.
6. Confirm file ownership and permissions match the existing application.

## 5. Run server-side acceptance

From the deployed application directory:

```bash
bash ./run-full-syntax-check.sh
bash ./run-quality-gate.sh
php ./bin/product-acceptance.php
```

Then open the administrator page:

```text
/admin/live-acceptance.php
```

The page checks PHP, required extensions, private config readiness, database/schema acceptance, safe runtime settings, release tools, and Stage 890–899 gate state. It performs reads only.

## 6. Run anonymous HTTPS smoke tests

```bash
TL_PUBLIC_BASE_URL=https://labs.example.com \
php ./bin/live-acceptance.php
```

The smoke test:

- requires HTTPS except for localhost development;
- sends GET requests only;
- checks public pages;
- confirms participant and administrator routes redirect anonymous users to sign-in;
- verifies request ID, content-type, frame, referrer, permissions, CSP, and HSTS headers;
- refuses credentials, query strings, or fragments in the base URL;
- never follows redirects into another origin.

## 7. Run role-based live acceptance

Create temporary, least-privilege test sessions for participant, reviewer, manager, and administrator accounts. Supply the complete cookie header through protected environment variables:

```bash
export TL_ACCEPTANCE_PARTICIPANT_COOKIE='microgifter_training_lab=...'
export TL_ACCEPTANCE_REVIEWER_COOKIE='microgifter_training_lab=...'
export TL_ACCEPTANCE_MANAGER_COOKIE='microgifter_training_lab=...'
export TL_ACCEPTANCE_ADMIN_COOKIE='microgifter_training_lab=...'

TL_PUBLIC_BASE_URL=https://labs.example.com \
php ./bin/live-acceptance.php --require-role-sessions
```

Cookie values are used only as request headers and are never printed in the report. Clear the variables immediately after testing:

```bash
unset TL_ACCEPTANCE_PARTICIPANT_COOKIE TL_ACCEPTANCE_REVIEWER_COOKIE \
      TL_ACCEPTANCE_MANAGER_COOKIE TL_ACCEPTANCE_ADMIN_COOKIE
```

The role test confirms expected product routes return HTTP 200 and protected administrator routes do not return HTTP 200 for lower roles.

## 8. Save an acceptance artifact

```bash
TL_PUBLIC_BASE_URL=https://labs.example.com \
php ./bin/live-acceptance.php --require-role-sessions --json \
  --output=./var/acceptance/live-acceptance.json
```

Store the report outside the public web path when possible. The report contains statuses and route names, not cookie values or private configuration.

## Rollback

If any production check fails after deployment:

1. Stop the deployment and keep reward delivery gates at their prior state.
2. Restore the previous application file package.
3. Restore the preserved `/labs/config.php` only when it was changed outside this package workflow.
4. Do not delete Training Lab rows created before rollback.
5. Restore the database only when the deployment itself changed data outside the read-only acceptance process.
6. Re-run syntax, the quality gate, product acceptance, and anonymous live acceptance against the restored version.
7. Record the failed release SHA and the rollback package SHA.

## Authority and safety boundary

Production readiness and live acceptance do not import SQL, change configuration, create accounts or roles, submit proof, review proof, issue or claim rewards, mutate wallets, run workers, reconcile delivery, or contact Microgifter APIs. All Stage 890–899 production controls remain independently gated.

## SQL

No SQL is required for Section 14.
