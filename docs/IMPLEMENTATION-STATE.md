# Employer Verification & Resume Experience — Implementation State

## Project objective

Implement the approved employer-verification workflow, approved-employer-only
Resume Directory shortcode, and secure Contact Candidate workflow inside this
plugin only. Preserve existing Click2EU membership, WooCommerce, HPOS,
renewal, upgrade, expiration, payment-notice and notification behavior.

## Baseline and branch

| Item | Value |
|---|---|
| Baseline plugin version | 0.9.2 |
| Verified baseline commit | `c74c3727464616021f7e41a7e874c36dafa03680` |
| Baseline branch | `master` / `origin/master` |
| Current implementation branch | `staging` / `origin/staging` |
| Current implementation commit | `eeb4238ead361ee5e2906799871dbc2f82a2abd2` |
| Overall completion | 5% — Stage 0 source/environment audit completed; no product code implemented |

## Current stage

**Stage 0 — Repository and Environment Audit: PASS with runtime limitations.**

The approved plan was read completely. Its stated reviewed baseline (v0.7.4
on `develop`) is historical; the authoritative current baseline is v0.9.2 on
the synchronized `master`. The old `develop` branch is not being used.

## Completed tasks

- **PASS** Repository root verified: `wp-content/plugins/click2eu-membership`.
- **PASS** Local `master` was clean and exactly matched `origin/master` at
  `c74c372` before `staging` was created.
- **PASS** New `staging` branch was created from the verified master baseline
  and pushed to `origin/staging`.
- **PASS** Main header, bootstrap constant and config constant all report
  plugin version `0.9.2`.
- **PASS** Approved source-of-truth plan read:
  `docs/click2eu-employer-verification-resume-plan.md`.
- **PASS** Parent theme, child theme, Cariera Core/Addons and WP Job Manager
  sources inspected without modifying vendor/theme files.
- **PASS** Actual Invite Candidate display callback identified in
  `Cariera_Core\Extensions\Resume\Invite_Candidate`:
  `display_invite_candidate()` is attached at priority 100 to
  `single_resume_meta_end` for V1 and at priority 50 to
  `cariera_candidate_detail_actions` for V2/V3.
- **PASS** Existing Employer bypasses located in `Click2EU_Route_Policy` and
  `Click2EU_Access`; they are role-only today and require Stage 2 conversion
  to approved-employer checks.
- **PASS** Child theme has only stylesheet enqueueing; no relevant custom
  registration or Resume integration source was found.

## Current source findings

- Cariera Core: 2.0.2; Cariera Addons: 1.1.1; WP Job Manager: 2.4.10.
- Resume functionality is supplied by Cariera Addons when standalone WP Resume
  Manager is absent. The installed Addons `Resumes` module provides the
  `resume` post type compatibility layer.
- V2 and V3 templates expose `cariera_resume_actions`; the exact vendor Invite
  action uses `cariera_candidate_detail_actions`, not that shared action.
- V1 does not contain the hook directly; the vendor class attaches its callback
  to `single_resume_meta_end`, which must be verified at runtime before Stage 3.
- The installed Cariera registration template has no Company Name or Company
  Registration Number inputs. No matching field/persistence customization was
  found in `cariera-child`. The documented field source is therefore **not
  identified**; Stage 2 must not invent duplicate fields without resolving this
  discrepancy.

## Status by stage

| Stage | Status | Evidence / next condition |
|---|---|---|
| 0. Repository and environment audit | PASS | Source audit completed; database runtime evidence is blocked. |
| 1. Resume Directory redesign | NOT STARTED | Must fail closed until approved-employer helper exists. |
| 2. Employer Verification and admin approval | NOT STARTED | Registration field-source discrepancy must be resolved before implementation. |
| 3. Contact Candidate integration | NOT STARTED | Exact display callback is known; runtime layout verification remains required. |
| Final security/regression/documentation | NOT STARTED | Requires completed stages and restored local runtime. |

## Blockers

1. **BLOCKED — Local WordPress database runtime:** `wp-load.php` failed with
   `mysqli_real_connect(): connection refused` to the configured local DB host.
   Runtime confirmation of active plugins, Resume page content/ID, taxonomies,
   shortcodes and actual registration output cannot be claimed until LocalWP's
   database service is running.
2. **BLOCKED — Employer registration field provenance:** neither the Cariera
   Core registration template nor child theme contains the reported Company
   Name / Company Registration Number inputs. Locate the active source after
   runtime is restored, or obtain owner direction before adding fields.
3. **NOT TESTED — Cariera browser behavior:** no real registration, Resume
   archive or V1/V2/V3 browser test has been run. Source inspection is not
   runtime verification.

## Last successful tests

| Check | Command/procedure | Result |
|---|---|---|
| Git baseline synchronization | `git fetch origin`; compare `master` with `origin/master` | PASS |
| Staging creation/push | `git switch -c staging`; `git push -u origin staging` | PASS |
| Version audit | `rg` across header/bootstrap/config/README | PASS — 0.9.2 |
| Vendor source audit | `rg`/source review of Cariera, Addons, parent/child themes | PASS |
| WordPress runtime audit | PHP 8.4 bootstrap of temporary read-only audit | BLOCKED — database connection refused |
| Automated test discovery | searched for PHPUnit/composer/package/test files | NOT TESTED — no repository harness found |

## Failed tests

None. The runtime audit was blocked by unavailable database connectivity, not a
plugin assertion failure.

## Browser QA status

**BLOCKED.** Local database service was unavailable. No browser test was run in
this implementation session.

## Known issues

- The planning document reflects an older v0.7.4/develop audit and must not be
  treated as evidence that the v0.9.2 baseline has identical runtime behavior.
- Current role-only Employer authorization is intentionally unchanged until the
  dedicated verification module and its registration integration are designed
  against verified runtime facts.

## Exact next action

Restore the LocalWP database service, then re-run the Stage 0 runtime audit to
identify: active plugins, active theme, existing Resume page ID/content,
`resume_category`/`resume_skill` availability, actual registration page output,
and the source/field names for Company Name and Company Registration Number.
Do not begin Stage 1 or Stage 2 implementation until that discrepancy is
resolved and this state file is updated.
