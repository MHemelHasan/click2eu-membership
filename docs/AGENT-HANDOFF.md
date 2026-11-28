# Agent Handoff - Stage 3 Candidate Contact

## Repository state

- Repository: `MHemelHasan/click2eu-membership`
- Branch: `staging`; do not use stale `develop` for this work.
- Plugin version: `0.9.2`
- Baseline: `c74c3727464616021f7e41a7e874c36dafa03680`
- Stage 3 implementation commit: `ee7185953f4baf3374f7c62129f75d8d8bc174e9`
- Do not edit Cariera, WP Job Manager, WordPress core, themes, or production.

## Completed

Stage 2 was audited before Stage 3. Missing standard-registration validation,
admin pagination, and two role-only Employer authorization paths were fixed.

Stage 3 is implemented in `includes/class-click2eu-candidate-contact.php`, with
`assets/candidate-contact.js` and `assets/candidate-contact.css`. It removes
only Cariera's exact Invite Candidate callback, adds an approved-Employer/Admin
Contact Candidate dialog, keeps Candidate email server-side, and applies nonce,
authorization, idempotency, cooldown, and hourly-limit checks.

## Test state

- Automated checks: PASS, 227/227 assertions.
- PHP 8.2 and 8.4 lint: PASS.
- Licensed Cariera browser tests: NOT TESTED/BLOCKED locally.
- SMTP and inbox delivery: NOT TESTED.

Use `docs/STAGE3-VISUAL-TEST-GUIDE.md`. Do not call Stage 3 release-accepted
until V1/V2/V3, denied states, duplicate, rate-limit, and email tests are
owner-confirmed.

## Next action

Collect the owner's checklist. Repair failures on `staging`; otherwise begin
the final security/regression checkpoint. Do not merge to `master` without
explicit authorization.
