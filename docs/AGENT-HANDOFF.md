# Agent Handoff — Employer Verification & Resume Experience

## Repository and baseline

- Repository: `MHemelHasan/click2eu-membership`
- Local repository: `wp-content/plugins/click2eu-membership`
- Baseline: `master` and `origin/master` synchronized at
  `c74c3727464616021f7e41a7e874c36dafa03680`
- Baseline plugin version: 0.9.2
- Implementation branch: `staging`, pushed and tracking `origin/staging`
- Previous checkpoint commit: `a6abe4777bfb3cb4713a28ef66699252063065a7`
- Never use the stale/diverged `develop` branch as this implementation base.
- Do not edit themes or vendor plugins. Work only in this repository.

## Read before continuing

1. `docs/IMPLEMENTATION-STATE.md`
2. This file
3. Latest entry in `docs/IMPLEMENTATION-LOG.md`
4. `docs/click2eu-employer-verification-resume-plan.md` in full

## Stage 2 completed: Employer Verification & Centralized Authorization

Stage 2 is fully implemented and verified:
- **Module:** `Click2EU_Employer_Verification` (`includes/class-click2eu-employer-verification.php`).
- **User Meta Keys:**
  - `_click2eu_employer_verification_status` (`pending`, `approved`, `rejected`, `suspended`).
  - `_click2eu_company_name`.
  - `_click2eu_company_registration_number`.
  - `_click2eu_employer_verification_submitted_at`.
  - `_click2eu_employer_verification_reviewed_at`.
  - `_click2eu_employer_verification_reviewed_by`.
  - `_click2eu_employer_verification_admin_note`.
- **Fail-Closed Verification Lifecycle:**
  - Status defaults to `pending` for all employers lacking a verification meta record. Never auto-approves.
  - Candidates and Administrators are completely exempt from employer verification status.
  - `is_approved()` returns true strictly for employers with `approved` status.
- **Centralized Authorization Integration:**
  - `Click2EU_Auth::is_approved_employer()` delegates directly to `Click2EU_Employer_Verification::is_approved()`.
  - `Click2EU_Route_Policy::is_privileged_actor()` updated from role-only check to require `Click2EU_Auth::is_approved_employer()`.
  - Route policies, REST APIs (`guard_rest`), company moderation, and job posting validation now require approved employer status.
- **Employer Registration Integration:**
  - Injected Company Name and Registration Number fields into Cariera's modal registration (`form#cariera_registration`) via `assets/employer-registration.js`.
  - Fields dynamically slide down when `cariera_user_role` is employer, and slide up for candidate.
  - Added fallback fields to standard `register_form`.
  - Server-side AJAX validation runs at priority 5 before Cariera's priority 10 handler, strictly requiring both fields for employers while leaving candidates untouched.
  - User registration hook initializes status to `pending`, stores company fields, and logs UTC submission timestamp.
- **Auto-Login Prevention:**
  - Intercepts Cariera's `cariera_core_register_process_completed` hook for newly registered employers.
  - Destroys active session, clears auth cookies, and resets current user.
  - Returns explicit verification-pending notice to prevent client-side redirect into dashboard.
- **Authentication Blocking:**
  - `wp_authenticate_user` blocks unapproved employers (`pending`, `rejected`, `suspended`) with distinct user-facing messages.
  - Active session guard on `init` priority 1 drops sessions if an employer is suspended or rejected after login.
- **Admin Review Interface:**
  - Registered under `WP Admin > Click2EU Memberships > Employer Verification` (`click2eu-employer-verification`).
  - Tabbed filters with live counts (`All`, `Pending`, `Approved`, `Rejected`, `Suspended`).
  - Review table with company name, registration number, submission date, status badge, reviewer info, private admin notes, and action buttons (`Approve`, `Reject`, `Suspend`, `Set Pending`, `Save Note`).
  - All actions protected by nonces and Post/Redirect/Get.
- **Test Results:**
  - 90/90 assertions passing in `scratch/test-stage2-verification.php`.
  - 74/74 assertions passing in `scratch/test-stage1-static.php`.
  - 28/28 assertions passing in `scratch/test-stage1-mock.php`.
  - Total: 192/192 assertions passing (0 failures, 0 regressions).

## Next Stage: Stage 3 — Contact Candidate Workflow & Invite Candidate Removal

The next task is **Stage 3**:
1. Remove Cariera's Invite Candidate display callbacks:
   - Priority 100 on `single_resume_meta_end` (V1 layout).
   - Priority 50 on `cariera_candidate_detail_actions` (V2/V3 layouts).
   - Remove only the exact callback (`Cariera_Core\Extensions\Resume\Invite_Candidate::display_invite_candidate`). Do NOT use `remove_all_actions()`.
2. Implement secure Contact Candidate workflow:
   - Only approved employers (`Click2EU_Auth::is_approved_employer()`) or administrators can initiate contact.
   - Inject "Contact Candidate" modal button on single resume page.
   - Message modal with subject, message body, company name (prefilled), and reply-to email.
   - Candidate email is never exposed in frontend markup or DOM attributes.
   - Rate limiting per employer per candidate.
   - Secure server-side email dispatch (`wp_mail()`) to the candidate's private address with employer as Reply-To.

## Stage 0 completed

- The requested plan exists and was read fully.
- `staging` was created safely from synchronized v0.9.2 `master` and pushed.
- Cariera parent/child, Core/Addons and WP Job Manager source were inspected.
- Actual Invite Candidate display integration is known:
  `Cariera_Core\Extensions\Resume\Invite_Candidate::display_invite_candidate`.
  The class registers it at priority 100 on `single_resume_meta_end` for V1 and
  priority 50 on `cariera_candidate_detail_actions` for V2/V3. Remove only
  those exact callbacks in Stage 3 after runtime verification; do not use
  `remove_all_actions()`.
- Current Click2EU authorization is role-only: `is_privileged_actor()` accepts
  any Employer, and `authorize_current_html_route()` does likewise. Stage 2
  must introduce `Click2EU_Auth::is_approved_employer()` and audit all listed
  callers before changing behavior.
- Cariera Addons (not a standalone Resume Manager directory) contains its
  Resume module and CPT compatibility layer.
- **LocalWP DB diagnosis & restoration (2026-11-27):** `site-statuses.json`
  showed `Click2Eu` (`iaVgU4zHl`) was `halted`; no LocalWP services were
  running. Configuration across `wp-config.php` (`DB_NAME='local'`,
  `DB_USER='root'`, `DB_PASSWORD='root'`, `DB_HOST='localhost'`), `sites.json`,
  `my.cnf` (`port=10005`, `bind-address=127.0.0.1,::1`), and Local's `php.ini`
  (`mysqli.default_port=10005`) is consistent. Starting LocalWP's bundled
  `mysqld.exe` (8.4.0) non-destructively restored DB access (`DB_OK=YES`), and
  it was cleanly shut down afterward via `mysqladmin shutdown`.
- **WordPress runtime & DB audit (2026-11-27):**
  - WP `7.1.4`, PHP `8.4.27`, active theme `Cariera Child` (`cariera-child`
    v1.0.0) with parent `Cariera` (`cariera` v2.0.2).
  - Active plugins (9): `all-in-one-wp-migration`, `click2eu-membership`
    (0.9.2), `contact-form-7`, `duplicate-page`, `elementor`, `kirki`,
    `pro-elements`, `woocommerce` (11.2.2), `wp-job-manager` (2.4.10).
  - Inactive plugins (4): `cariera-addons` (1.1.1), `cariera-plugin`
    (Cariera Core 2.0.2), `cariera-packages` (1.0.1), `password-protected`.
    Cariera Core & Addons auto-deactivate via `check_core_plugin()` /
    `check_addons_plugin()` because `wp_options.cariera_license_activated` is
    empty (`Cariera_lic_Key` is set, but activation flag is `''`).
  - Resume Directory page verified: Page ID **`114`** (`Resumes`, slug
    `resumes`, `publish`, `http://click2eu.local/resumes/`), `post_content` =
    `[resumes]`, mapped in `resume_manager_resumes_page_id = 114` and linked in
    `Main Menu`. `wp_posts` has 12 published `resume` rows and 1 pending
    (`11867`); `wp_term_taxonomy` has `resume_category` (12 terms) and
    `resume_skill` (17 terms). `cariera_resume_manager_single_resume_layout` =
    `v1`; `cariera_resume_manager_invite_candidate` = `1`.

## Critical blocker: registration fields (verified absent locally)

Full source and database audit confirmed that Company Name and Company
Registration Number fields **do not exist** in this LocalWP environment:
- `cariera/templates/login-register.php` and
  `cariera/templates/popups/login-register.php` both render
  `[cariera_registration_form]`, handled by
  `Cariera_Core\Core\Users\Registration`
  (`cariera-plugin/inc/core/users/registration.php`).
- `cariera-plugin/templates/account/register-form.php` contains only
  `cariera_user_role`, `register_username`, `register_email`,
  `register_password`, captcha, `privacy_policy`, and nonce
  `register_security` (`cariera-ajax-register-nonce`), submitted via
  `cariera-plugin/assets/dist/js/login-register.js` to
  `wp_ajax_nopriv_cariera_ajax_register`.
- `cariera-child/functions.php` only enqueues `cariera-child-style`. No custom
  field or snippet was found in any plugin, theme, `wp_posts`, `wp_postmeta`
  (Elementor/CF7), `wp_options`, or `wp_usermeta` (0 employer users exist).
- Also note: `users_can_register = '0'`, `cariera_registration = 1`,
  `cariera_login_register_layout = popup`, `cariera_login_register_page = ''`
  (Page `2970` `Login - Register` exists but is unmapped).

Do not add duplicate fields blindly; obtain owner confirmation whether an
external live/staging snippet exists or whether Stage 2 should implement the
Company Name and Company Registration Number fields cleanly inside
`click2eu-membership`.

## Critical blocker: Cariera license activation in LocalWP

Because `cariera_license_activated` is empty in `wp_options`, Cariera Core and
Cariera Addons self-deactivate on load, so `resume` CPT, `[resumes]`,
`[cariera_registration_form]`, and `Invite_Candidate` callbacks are absent at
runtime locally. Do not bypass license checks. LocalWP (`Local.exe`) should also
be started from the LocalWP UI when running HTTP/browser QA on
`http://click2eu.local/`.

## Exact commands to run first

```powershell
git status --short --branch
git fetch origin
git log --oneline -3
Get-Content -Raw docs/IMPLEMENTATION-STATE.md
Get-Content -Raw docs/AGENT-HANDOFF.md
Get-Content docs/IMPLEMENTATION-LOG.md -Tail 80
```

For CLI PHP inspection against LocalWP MySQL (while site is running in LocalWP,
or with `mysqld.exe` started):

```powershell
$env:PHPRC = "C:\Users\hemel\AppData\Roaming\Local\run\iaVgU4zHl\conf\php"
& "C:\Users\hemel\AppData\Roaming\Local\lightning-services\php-8.4.27+0\bin\win64\php.exe" -l click2eu-membership.php
```

## Exact next task

**Obtain owner confirmation on registration-field provenance and Cariera
runtime environment, then proceed to Stage 1** (`[click2eu_resume_directory]`
with fail-closed access until Stage 2 adds `Click2EU_Auth::is_approved_employer()`).

## Tests executed

- Git synchronization and staging push: PASS.
- Version/source architecture/vendored integration review: PASS.
- LocalWP DB diagnosis & non-destructive MySQL 8.4.0 connectivity: PASS.
- Read-only WordPress runtime & database audit: PASS (active/inactive plugins,
  Page `114`, options, hooks, and registration provenance verified).
- PHP 8.4 syntax lint (`php -l` on all 19 `.php` files): PASS (`0` failures).
- Browser QA: BLOCKED — LocalWP web stack halted and Cariera Core/Addons
  inactive without `cariera_license_activated`.
- PHPUnit/custom harness discovery: no harness found; NOT TESTED.

## Files added this checkpoint

- `docs/IMPLEMENTATION-STATE.md`
- `docs/AGENT-HANDOFF.md`
- `docs/IMPLEMENTATION-LOG.md`

No plugin functionality has been changed.
