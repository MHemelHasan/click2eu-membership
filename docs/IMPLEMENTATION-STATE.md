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
| Previous checkpoint commit | `e08069a7fb3140173a2a8e6bf0a7ef3114984265` |
| Overall completion | 10% — Stage 0 source + LocalWP DB/runtime audit completed; no product code implemented |

## Current stage

**Stage 0 — Repository, LocalWP Database, and WordPress Runtime Audit: COMPLETED (with documented Cariera license & registration-field blockers).**

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
- **PASS** LocalWP database diagnosis completed: `site-statuses.json` showed
  site `iaVgU4zHl` (`Click2Eu`) was `halted` and no LocalWP services were
  running. Starting LocalWP's bundled `mysqld.exe` (MySQL 8.4.0 on port
  `10005` with `--defaults-file=.../run/iaVgU4zHl/conf/mysql/my.cnf`) restored
  DB connectivity cleanly without modifying data (`mysqld is alive`; later
  shut down cleanly via `mysqladmin shutdown`).
- **PASS** Read-only WordPress runtime & database audit completed (PHP 8.4.27
  with site `PHPRC`, WP 7.1.4, DB `local`, prefix `wp_`).
- **PASS** PHP 8.4 syntax lint across all 19 `.php` files in
  `click2eu-membership/` (`FAIL=0`).

## Current source and runtime findings

- **Runtime environment:** WordPress `7.1.4`, PHP `8.4.27`, MySQL `8.4.0`
  (port `10005`, `DB_HOST='localhost'` relying on LocalWP `php.ini`
  `mysqli.default_port = 10005`), `siteurl`/`home` = `http://click2eu.local`,
  `admin_email` = `asifreza979@gmail.com`, table prefix `wp_`, 0 MU-plugins,
  no `db.php`/`object-cache.php`/`advanced-cache.php` drop-ins.
- **Active theme:** `Cariera Child` (`cariera-child` v1.0.0), parent `Cariera`
  (`cariera` v2.0.2). `cariera-child/functions.php` only enqueues
  `cariera-child-style`; no template overrides exist in `cariera-child/`.
- **Active plugins (9):** `all-in-one-wp-migration` (7.113),
  `click2eu-membership` (0.9.2), `contact-form-7` (6.2.4.1), `duplicate-page`
  (4.5.9), `elementor` (4.3.6), `kirki` (6.4.1), `pro-elements` (4.3.2),
  `woocommerce` (11.2.2), `wp-job-manager` (2.4.10).
- **Inactive plugins (4):** `cariera-addons/cariera-addons.php` (1.1.1),
  `cariera-plugin/cariera-core.php` (2.0.2),
  `cariera-packages/cariera-packages.php` (1.0.1), `password-protected`
  (2.8.6).
- **Why Cariera Core / Addons are inactive at runtime:**
  `Cariera_Core::check_core_plugin()` (`cariera-plugin/cariera-core.php:193-207`)
  and `Cariera_Addons::check_addons_plugin()` (`cariera-addons/cariera-addons.php:223-237`)
  call `deactivate_plugins()` whenever `get_option('cariera_license_activated')`
  or `get_option('Cariera_lic_Key')` is empty. In `wp_options`,
  `Cariera_lic_Key` is present (`0caa57b5-8d15-4485-9564-44016ca789e2`), but
  `cariera_license_activated` is `''`. Consequently, at runtime
  `post_type_exists('resume')` = `NO`, `shortcode_exists('resumes')` = `NO`,
  and `shortcode_exists('cariera_registration_form')` = `NO`; Cariera Core/Addons
  hooks (`single_resume_meta_end`, `cariera_candidate_detail_actions`,
  `wp_ajax_nopriv_cariera_ajax_register`) have no registered callbacks in this
  inactive local runtime.
- **Resume Directory page & DB state:**
  - Primary Resume page ID **`114`** (`Resumes`, slug `resumes`, `publish`,
    permalink `http://click2eu.local/resumes/`), `post_content` = `[resumes]`,
    mapped in `wp_options.resume_manager_resumes_page_id = 114` and linked in
    `Main Menu` (`term_id=111`, item `3246`).
  - Related mapped/demo pages in DB: `118` `Submit Resume`
    (`[submit_resume_form]`), `120` `Candidate Dashboard`
    (`[candidate_dashboard]`), `11958` `Join Us` (`[click2eu_packages_section]`),
    plus Cariera Elementor demo resume layouts (`59`, `116`, `122`, `124`,
    `126`, `128`, `7464`, `9798`, `10465`, `11814`).
  - DB `wp_posts` contains `12` published `resume` posts (author `1` `asif`)
    and `1` pending `resume` post (`11867`, author `3` `Hasan`).
  - DB `wp_term_taxonomy` contains `resume_category` (12 terms), `resume_skill`
    (17 terms), `resume_education_level` (5 terms), `resume_experience` (4 terms).
  - Relevant options in `wp_options`: `resume_manager_enable_categories = 1`,
    `resume_manager_enable_skills = 1`, `resume_manager_per_page = 10`,
    `cariera_resume_manager_single_resume_layout = v1`,
    `cariera_resume_manager_invite_candidate = 1`,
    `cariera_resume_manager_invite_candidate_employer_email = user_email`.
  - Click2EU options: `click2eu_join_page_id = 11958`,
    `click2eu_membership_db_version = 3.0.0`,
    `click2eu_notification_db_version = 1.1.0`; `click2eu_phase1_settings` and
    `click2eu_resume_category_map` are not yet saved in `wp_options`
    (settings fall back to `Click2EU_Settings::defaults()`).
- **Registration rendering & field provenance (unresolved / absent locally):**
  - `wp_options`: `users_can_register = '0'`, `cariera_registration = 1`,
    `cariera_login_register_layout = popup`, `cariera_login_register_page = ''`
    (published Page `2970` `Login - Register` with template
    `templates/login-register.php` exists but is unmapped),
    `cariera_user_role_candidate = 1`, `cariera_user_role_employer = 1`,
    `cariera_default_registration_user_role = employer`,
    `cariera_moderate_new_user = auto`, `cariera_auto_login = 1`,
    `cariera_register_hide_username = 0`, `cariera_register_privacy_policy = 1`.
  - Both `cariera/templates/login-register.php:77` and
    `cariera/templates/popups/login-register.php:47` (plus
    `cariera-plugin/inc/elementor/login_register.php:90`) render
    `[cariera_registration_form]`, handled by
    `Cariera_Core\Core\Users\Registration`
    (`cariera-plugin/inc/core/users/registration.php`), which renders
    `cariera-plugin/templates/account/register-form.php` and posts via
    `cariera-plugin/assets/dist/js/login-register.js` to
    `wp_ajax_nopriv_cariera_ajax_register`.
  - `register-form.php` has **only** `cariera_user_role` (`candidate`/`employer`,
    with `candidate` gated on `class_exists('WP_Resume_Manager')`),
    `register_username`, `register_email`, `register_password`, optional
    captcha, `privacy_policy`, and nonce `register_security`
    (`cariera-ajax-register-nonce`). It has no hooks inside `<form>` (only
    `cariera_register_form_before` and `cariera_register_form_after` outside
    `<form>`).
  - Full inspection of all active/inactive plugins (`cariera-plugin`,
    `cariera-addons`, `click2eu-membership`, `pro-elements`, `woocommerce`),
    parent/child themes, `wp_posts`, `wp_postmeta` (Elementor & CF7),
    `wp_options`, and `wp_usermeta` (`wp_users` has 3 users: 2 admins, 1
    candidate, **0 employers**) found **no Company Name or Company
    Registration Number registration fields anywhere in the local codebase
    or database**.
  - `Click2EU_Cariera::validate_cariera_registration_role()` currently validates
    only `cariera_user_role` on `cariera_ajax_register`.
- **Cariera Addons Resume API note for Stage 1:**
  - In `cariera-addons/inc/core/resumes/shortcodes.php` (`[resumes]`) and
    `cariera-addons/inc/core/resumes/post-types.php`: `resume` CPT,
    `resume_category`, and `resume_skill` are registered by Cariera Addons
    (not a standalone WPJM Resumes plugin). Stage 1's `[click2eu_resume_directory]`
    should query `WP_Query` on `post_type = 'resume'` cleanly (and guard
    taxonomy filters with `taxonomy_exists()`), failing closed until Stage 2.

## Status by stage

| Stage | Status | Evidence / next condition |
|---|---|---|
| 0. Repository and environment audit | PASS (runtime DB restored; blockers documented) | DB + WP runtime + Resume page + Cariera state verified; registration field provenance absent locally and Cariera Core/Addons inactive due to empty `cariera_license_activated`. |
| 1. Resume Directory redesign | READY TO START AFTER CONFIRMATION (fail-closed) | Page `114` (`[resumes]`) verified; must fail closed until Stage 2 adds approved-employer helper. Note: `resume` CPT/taxonomies require Cariera Addons active in live/staging runtime. |
| 2. Employer Verification and admin approval | BLOCKED ON OWNER DIRECTION FOR REGISTRATION FIELDS | No existing Company Name / Company Registration Number fields exist in local registration source/DB; owner must confirm whether a remote/live snippet exists or authorize Stage 2 to add them cleanly. |
| 3. Contact Candidate integration | NOT STARTED | Exact display callback is known (`Invite_Candidate::display_invite_candidate`); runtime layout verification on V1/V2/V3 requires licensed Cariera Core/Addons. |
| Final security/regression/documentation | NOT STARTED | Requires completed stages and licensed Cariera runtime. |

## Blockers

1. **BLOCKED (Registration field provenance — owner decision needed):** Neither
   `cariera-plugin/templates/account/register-form.php`, `cariera-child`, other
   installed plugins, nor the LocalWP database (`wp_posts`, `wp_postmeta`,
   `wp_options`, `wp_usermeta`) contains Company Name or Company Registration
   Number registration inputs. Confirm with the site owner whether an external
   live/staging snippet exists outside this LocalWP site or whether Stage 2
   should implement these fields directly in `click2eu-membership` (preserving
   Cariera's `form#cariera_registration` / `cariera_ajax_register` contract).
2. **BLOCKED (Local Cariera license activation — vendor runtime):** Cariera
   Core (`2.0.2`), Cariera Addons (`1.1.1`), and Cariera Packages (`1.0.1`)
   auto-deactivate in LocalWP because `cariera_license_activated` is empty in
   `wp_options`. Do not bypass license checks; live/browser verification of
   Cariera registration, `[resumes]` / `resume` CPT runtime, and V1/V2/V3
   single-resume hooks remains blocked locally until legitimately activated on
   staging/local.
3. **ENVIRONMENT NOTE (LocalWP halted when idle):** LocalWP `Click2Eu`
   (`iaVgU4zHl`) is halted when Local.exe is closed; start the site in LocalWP
   before browser/HTTP testing on `http://click2eu.local/`, and pass
   `PHPRC=C:\Users\hemel\AppData\Roaming\Local\run\iaVgU4zHl\conf\php` for CLI
   PHP scripts so `mysqli.default_port = 10005` matches `DB_HOST='localhost'`.
4. **NOT TESTED — Cariera browser behavior:** No real registration, Resume
   archive or V1/V2/V3 browser test has been run.

## Last successful tests

| Check | Command/procedure | Result |
|---|---|---|
| Git baseline synchronization | `git fetch origin`; compare `master` with `origin/master` | PASS |
| Staging creation/push | `git switch -c staging`; `git push -u origin staging` | PASS |
| Version audit | `rg` across header/bootstrap/config/README | PASS — 0.9.2 |
| Vendor source audit | source review of Cariera, Addons, parent/child themes | PASS |
| LocalWP MySQL service & DB connectivity | `mysqld.exe --defaults-file=.../my.cnf`; `mysqladmin ping`; `$wpdb->get_var('SELECT 1')` | PASS (`mysqld is alive`, MySQL 8.4.0, `DB_OK=YES`) |
| WordPress read-only runtime & DB audit | PHP 8.4.27 (`PHPRC` set) bootstrap + MySQL read-only queries | PASS (revealed active/inactive plugins, Page `114`, options, hooks, and absence of local registration company fields) |
| PHP 8.4 syntax lint | `php -l` across all 19 `.php` files in `click2eu-membership` | PASS (`19` files, `0` failures) |
| Automated test discovery | searched for PHPUnit/composer/package/test files | NOT TESTED — no repository harness found |

## Failed tests

None.

## Browser QA status

**BLOCKED.** LocalWP Nginx/PHP-CGI were halted (only `mysqld.exe` was started
temporarily for CLI audit and cleanly shut down), and Cariera Core/Addons
auto-deactivate locally without `cariera_license_activated`.

## Known issues

- The planning document reflects an older v0.7.4/develop audit and must not be
  treated as evidence that the v0.9.2 baseline has identical runtime behavior.
- Current role-only Employer authorization is intentionally unchanged until the
  dedicated verification module and its registration integration are designed
  against verified runtime facts.
- LocalWP CLI PHP emits a harmless startup warning for `php_imagick.dll` when
  ImageMagick's binary directory is not on `PATH`.

## Exact next action

1. Confirm with the site owner whether Company Name / Company Registration Number
   fields exist on a separate live/staging snippet outside LocalWP or should be
   implemented fresh by `click2eu-membership` in Stage 2 (and confirm whether a
   licensed staging site or activated LocalWP Cariera license will be used for
   Cariera-dependent browser verification).
2. Begin **Stage 1** (`[click2eu_resume_directory]` shortcode, search/filter UI,
   pagination, and fail-closed access control) only after confirming readiness
   to proceed with Stage 1's fail-closed directory before Stage 2's Employer
   verification helper.
