# Training Lab Stage 8 Auth Gate

Baseline: `training-lab-stage7-david-moveup-config.zip`

## Preserved

- Existing Stage 7 public/app/admin template and CSS.
- Working DB loader: `includes/training-lab-db.php`
- Working DB status endpoint: `api/training/db-status.php`
- Working final config location: `/labs/config.php`

## Added

- `includes/training-lab-auth-gate.php`
- `logout.php`
- Session handling in `signin.php`
- Session handling in `signup.php`
- Gate call inside `includes/labs-layout.php` for `app` and `admin` sections
- Gate call inside `api/training/actions/_action-bootstrap.php` for controlled write actions

## Config packaging rule

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

After David's move-up workflow, this lands as:

```text
/labs/config-example.php
```

The live private config remains:

```text
/labs/config.php
```

## Gate behavior

Public pages remain public.

App pages require participant or admin session.

Admin pages require admin session.

Action APIs require a session:
- Participant: join/submit proof
- Admin: create campaign/review proof/evaluate rewards/seed demo

`api/training/db-status.php` is intentionally untouched.
