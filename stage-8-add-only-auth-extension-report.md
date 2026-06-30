# Training Lab Stage 8 Add-Only Auth Extension

Baseline: `training-lab-stage7-david-moveup-config.zip`

## Lock-in rule followed

This package adds files only, except the package safety rename from `labs/labs/config.php` to `labs/labs/config-example.php` so David's live `/labs/config.php` is not overwritten.

Existing template files were not edited:
- `index.php`
- `signin.php`
- `signup.php`
- `admin/index.php`
- `app/index.php`
- `includes/labs-layout.php`
- `assets/css/labs.css`
- `assets/js/labs.js`
- `includes/training-lab-db.php`
- `api/training/db-status.php`

## New files

```text
includes/training-lab-auth-gate.php
api/training/auth-status.php
api/training/auth/login.php
api/training/auth/logout.php
admin/auth-gate.php
admin/secure-dashboard.php
app/secure-dashboard.php
stage-8-add-only-auth-extension-report.md
```

## Final URLs after David's move-up workflow

```text
/admin/auth-gate.php
/admin/secure-dashboard.php
/app/secure-dashboard.php
/api/training/auth-status.php
/api/training/auth/login.php
/api/training/auth/logout.php
/api/training/db-status.php
```

## Config packaging

The package includes:

```text
labs/labs/config-example.php
```

The package does not include:

```text
config.php
labs/config.php
labs/labs/config.php
```

After the move-up workflow, the config example lands at:

```text
/labs/config-example.php
```

David's live working config remains:

```text
/labs/config.php
```

## Safety

No real media uploads, no payments, no wallet balance changes, no reward issuing, no claim/redeem logic, and no duplicate production auth database.
