# Stage 882 Live Acceptance Gate Fix

Built after live `/api/training/live-smoke.php` showed:

- live routes: 100
- database smoke: 100
- adapter smoke: 100
- adapter dry run: 100
- Stage 881 deployment acceptance blocked only by live config placeholder checks

## Reason for fix

Stage 881 correctly checks that repository/archive config files remain sanitized with placeholder credentials.

On a deployed server, however, the private `labs/config.php` must contain real DB credentials. After the private config is restored, the Stage 881 placeholder check should not block Stage 882 live smoke acceptance when:

- source folders pass
- route presence passes
- validation coverage passes
- safe boundaries pass
- DB config path passes
- database smoke passes

## Change

`includes/training-lab-stage882-live-smoke.php` now adds a `stage881_live_gate` check.

The live gate accepts either:

1. Stage 881 full acceptance in a sanitized repo/archive context, or
2. A live private config context where the placeholder check is expected to fail but DB smoke and stable acceptance categories pass.

## Safety boundaries

- No new SQL.
- No config files moved or overwritten.
- No live credentials committed.
- No hard auth gates forced.
- No payment processing.
- No wallet mutation.
- No production claim/redeem mutation.
- No destructive Microgifter sync.
- Adapter dry run remains read-only.
