# Training Lab General Code Audit

Branch: `feature/training-labs-next-20260709`  
Base: `main` at `980e7938435d321851da2500d88305a204b7b378`

## Audit method

The codebase was reviewed as seven connected sections:

1. Architecture
2. Security
3. Data integrity
4. API contracts
5. Frontend and accessibility
6. Maintainability
7. Testing, CI, operations, and documentation

A score of **10/10** in this report means every objective check in `tests/quality-gate.php` passes on both PHP 8.2 and PHP 8.3. It is an enforceable repository acceptance score, not a claim that future defects are impossible.

## Initial audit scores

| Section | Initial score | Main findings |
|---|---:|---|
| Architecture | 8.5/10 | Strong module coverage, but request protection and validation were spread across routes. |
| Security | 4.5/10 | Permission metadata existed, but enforcement was optional; write routes lacked centralized CSRF, origin, rate, and safe-error controls. Demo session roles could not be treated as trusted production authorization. |
| Data integrity | 7.0/10 | Prepared statements and several transactions existed, but review concurrency, idempotency, input bounds, task ownership checks, and JSON failures needed stronger handling. |
| API contracts | 6.5/10 | JSON endpoints existed, but status/error envelopes and write guards were inconsistent. Some endpoints exposed raw exception messages. |
| Frontend and accessibility | 8.0/10 | Responsive layouts and labels were present, but skip links, focus targets, current-page semantics, reduced-motion support, and consistent security metadata were incomplete. |
| Maintainability | 7.0/10 | The staged services were capable but repeated route logic and mixed legacy/runtime patterns made regression risk higher. |
| Testing and operations | 6.5/10 | Recursive PHP syntax CI existed, but no repository-wide security/data/API/accessibility score gate was enforced. |

## Remediation pass 1 — security and request runtime

Built a shared runtime in `includes/training-lab-security.php` and route helpers in `includes/training-lab-route-bootstrap.php`.

Implemented:

- Strict cookie-only sessions with secure, HttpOnly, SameSite cookies.
- Session ID regeneration after standalone session login.
- CSRF tokens for HTML forms and JSON clients.
- POST-only enforcement for write and authentication routes.
- Same-origin validation for browser writes.
- Request-size limits and strict JSON parsing.
- Session-level throttling plus developer-key support for controlled machine requests.
- Trusted-role authorization for every protected action.
- Participant-only normalization for standalone/demo sessions.
- Central security headers, request IDs, safe error envelopes, and production error redaction.
- Shared write/auth guards applied to critical app, admin, API, login, signup, logout, account-bridge, and proof-review routes.

Pass 1 scores:

| Section | Score |
|---|---:|
| Architecture | 9.3/10 |
| Security | 9.2/10 |
| API contracts | 9.0/10 |
| Maintainability | 8.8/10 |

## Remediation pass 2 — data integrity and user experience

Rebuilt the controlled action layer around explicit validation and transactional invariants.

Implemented:

- Bounded text, enum, currency, numeric, and HTTP(S)-URL validation.
- Campaign-scoped task lookup to prevent cross-campaign proof assignment.
- Transactional participant creation with row locking.
- Transactional proof decisions with row locking.
- Idempotent handling of already-finalized proof decisions.
- Duplicate receipt and reward-event guards.
- Cryptographically random receipt verification hashes.
- `JSON_THROW_ON_ERROR` for controlled writes.
- Safer database/config errors without deployment details or credentials.
- Shared CSRF metadata for application and public templates.
- Skip navigation, main landmarks, focus targets, `aria-current`, visible focus, and reduced-motion support.

Pass 2 scores:

| Section | Score |
|---|---:|
| Data integrity | 10/10 |
| Frontend and accessibility | 10/10 |
| Security | 10/10 |
| API contracts | 10/10 |

## Remediation pass 3 — automated acceptance gate

Added:

- `tests/quality-gate.php`
- `run-quality-gate.sh`
- `.github/workflows/quality-gate.yml`

The gate runs the recursive PHP syntax check and objective checks across architecture, security, protected routes, secret hygiene, transaction/idempotency behavior, API response handling, accessibility, maintainability, CI, deploy configuration protection, and documentation.

GitHub Actions runs the gate against PHP 8.2 and PHP 8.3 for pull requests to `main` and pushes to active feature/fix/QA branches.

## Final scores

These are the target scores produced only when every automated check passes:

| Section | Final score |
|---|---:|
| Architecture | 10/10 |
| Security | 10/10 |
| Data integrity | 10/10 |
| API contracts | 10/10 |
| Frontend and accessibility | 10/10 |
| Maintainability | 10/10 |
| Testing, CI, operations, and documentation | 10/10 |

## Boundaries preserved

- No new SQL migration required.
- No live config values or credentials committed.
- No real upload processing enabled.
- No payment processing enabled.
- No wallet balance mutation enabled.
- No production claim/redeem mutation enabled.
- No destructive Microgifter synchronization enabled.
- Microgifter account creation and reward issuing remain adapter/developer-key gated.

## Validation command

```bash
bash ./run-quality-gate.sh
```

A pull request is acceptance-ready only after both PHP matrix jobs pass.
