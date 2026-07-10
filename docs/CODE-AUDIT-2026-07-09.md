# Training Lab General Code Audit — 2026-07-09

## Scope

Repository-wide audit of the standalone Training Lab PHP application on branch `feature/training-labs-next-20260709`. The review covered security/authentication, HTTP APIs, database integrity, architecture, frontend accessibility, testing/CI, and deployment operations.

A 10/10 score means every check in the versioned production-readiness rubric (`scripts/quality-audit.php`, rubric `2026-07-09.1`) passes. It does not mean that software can never contain an undiscovered defect.

## Initial audit

| Section | Initial score | Main findings |
|---|---:|---|
| Security & authentication | 4.5/10 | Optional permission enforcement, untrusted role elevation risk, no centralized CSRF/origin checks, weak session hardening, GET logout. |
| API & runtime behavior | 6.0/10 | Inconsistent method handling, raw exception disclosure, GET fallback in request parsing, no request IDs or payload limits. |
| Database & data integrity | 6.5/10 | Task/proof relationship not fully constrained, review race windows, predictable receipt hashes, inconsistent input bounds. |
| Architecture & maintainability | 7.0/10 | Strong staged service structure, but route security and error behavior were duplicated instead of centralized. |
| Frontend & accessibility | 7.5/10 | Output escaping was generally good; shared skip links, active-page semantics, focus support, and universal CSRF form support were missing. |
| Testing & CI | 4.0/10 | Recursive syntax lint existed, but no security, route, or data-integrity contract tests and no section scoring gate. |
| Deployment & operations | 8.0/10 | Strong config/archive protection and health routes; production error correlation and formal audit evidence were missing. |

## Remediation pass 1

### Security and authentication

- Added `includes/training-lab-security.php` as the central runtime security layer.
- Hardened session cookies with strict mode, cookie-only sessions, HttpOnly, SameSite, and HTTPS-aware Secure cookies.
- Added CSRF, same-origin, request method, payload size, request rate, and action permission enforcement.
- Added trusted-role normalization: local/demo sessions are always participants; elevated roles require a trusted Microgifter identity or developer key.
- Disabled standalone demo login by default when the database-backed deployment is active.
- Prevented adapter-pending production signups from silently becoming authenticated local accounts.
- Replaced GET logout with a CSRF-protected POST confirmation flow.

### API and runtime

- Added `includes/training-lab-route-bootstrap.php` for shared route behavior.
- Protected action, auth, app-action, account bridge, and Stage 885 review endpoints.
- Removed query-string fallback from state-changing request parsing.
- Added safe production errors, internal exception logging, HTTP status mapping, request IDs, JSON encoding failure handling, and security headers.

### Database integrity

- Added bounded input cleaners and strict enums.
- Restricted external proof links to valid HTTP(S) URLs.
- Ensured proof tasks belong to the selected campaign.
- Added row locks around participant joins, proof decisions, receipt creation, and reward duplicate checks.
- Made finalized review decisions idempotent.
- Replaced time-derived receipt hashes with cryptographically random verification material.
- Preserved transactions and rollback behavior for multi-row writes.

### Frontend and accessibility

- Added skip links, focusable main landmarks, `aria-current`, menu state semantics, keyboard close behavior, visible focus styles, and reduced-motion support.
- Added a shared CSRF meta token and automatic protection for legacy POST forms and same-origin JavaScript writes.

### Testing and CI

- Added security runtime tests.
- Added data-integrity contract tests.
- Added HTTP route contract tests.
- Added a deterministic seven-section scoring script.
- Added PHP 8.2 and PHP 8.3 quality-gate CI.

## Re-score after remediation pass 1

The quality gate requires all checks below to pass:

| Section | Required final score |
|---|---:|
| Security & authentication | 10/10 |
| API & runtime behavior | 10/10 |
| Database & data integrity | 10/10 |
| Architecture & maintainability | 10/10 |
| Frontend & accessibility | 10/10 |
| Testing & CI | 10/10 |
| Deployment & operations | 10/10 |

Run locally or in CI:

```bash
bash ./run-quality-gate.sh
```

The command exits nonzero whenever any audited section is below 10/10, making the audit repeatable rather than subjective.

## SQL and deployment impact

- No SQL migration required.
- No live config file moved or overwritten.
- No payment processing enabled.
- No wallet mutation enabled.
- No production claim/redeem mutation enabled.
- No destructive Microgifter sync enabled.
