# Phase-1 Launch Configuration and Acceptance Checklist

## Final artifact handoff

- [x] Final source version is consistently 0.7.4.
- [x] PHP 8.2 and PHP 8.4 lint pass.
- [x] WordPress initializes with WooCommerce 11.2.2 and HPOS enabled.
- [x] Final code artifact approved for controlled Cariera-enabled acceptance.
- [ ] Retain the working v0.7.3 ZIP before replacing the plugin.
- [ ] Verify production page and category mappings; never import the LocalWP DB.
- [ ] Clear plugin, page and CDN caches after installation.
- [ ] Verify actual persisted resume/application/order/membership records.
- [ ] Set CLIENT LAUNCH to GO only after every critical acceptance item passes.

## v0.7.4 RC1 final acceptance additions

- [x] Direct Join is public and contains no injected authentication forms.
- [x] Legacy `click2eu_auth=1` redirects without a loop.
- [x] Missing auth mapping falls back to WordPress login with local Join return.
- [ ] On controlled QA, map and verify the published Cariera Login / Registration page.
- [ ] Verify Candidate login: unpaid/Pending -> Join; Active -> Dashboard.
- [ ] Verify Candidate registration -> clean Join with no automatic cart item.
- [ ] Verify Employer login/registration keeps the Cariera employer destination.
- [ ] Verify password reset, logout and all WooCommerce continuation routes.
- [x] Notification admin CSS is scoped to Membership, Settings and Notifications.
- [x] Notification counts and bounded pagination render without full-row scans.
- [x] Uncertain notifications expose review guidance but no ordinary Retry action.
- [x] Approve final v0.7.4 artifact for controlled Cariera browser acceptance.

Status: **NO-GO until every critical staging row passes with legitimately active
Cariera Core/Addons.** This checklist is preparation only and authorizes no live
deployment.

## Configuration

- [ ] Record WordPress, PHP, WooCommerce, WP Job Manager, Cariera Core/Addons
  and Click2EU plugin versions.
- [ ] Confirm WooCommerce HPOS state and Classic versus Block Checkout.
- [ ] Confirm Candidate role slug `candidate` and Employer role slug `employer`.
- [ ] Confirm job/company/resume post types and their public/REST settings.
- [ ] Confirm `job_listing_category` and `resume_category` term IDs.
- [ ] Configure explicit `click2eu_resume_category_map` term-ID mappings.
- [ ] Configure all published Page Mapping fields in Click2EU Settings.
- [ ] Confirm My Account base and `/my-account/my-membership/` endpoint.
- [ ] Confirm product IDs 11868-11871, prices, currency and tax behavior against
  the approved commercial configuration.
- [ ] Confirm membership and notification schema versions and scheduled runners.
- [ ] Confirm SMTP/provider configuration without sending to real users during QA.
- [ ] Enable and verify company approval before Employer job submission.
- [ ] Apply the cache/CDN exclusions in `docs/DEPLOYMENT.md`.

## Read-only acceptance

- [ ] Guest homepage, Join, authentication and legal pages open.
- [ ] Guest job/company/resume/search/category routes redirect to Join.
- [ ] Protected AJAX/REST requests return structured 401/403.
- [ ] Search, feeds, sitemaps, metadata and cached variants disclose no protected
  job/company/resume or contact data.
- [ ] Unpaid Candidate Dashboard, Profile, Orders, My Membership and read-only My
  Resumes open without a paid entitlement.
- [ ] Active Candidate sees only exact entitled job categories.
- [ ] Administrator and Employer routes are never sent to Candidate Join solely
  because Candidate membership is absent.

## Isolated write acceptance

- [ ] Candidate package selection and Classic/Block Checkout create the expected
  HPOS order metadata; no auto-cart occurs after login.
- [ ] Pending payment grants no protected access; admin Completed activates once.
- [ ] Unpaid resume create/edit/preview, application, CV upload, Apply With Resume
  and Direct Message are denied before mutation/upload/email.
- [ ] Active matching Candidate can create a resume; persisted status is Pending
  Review; admin approval publishes it.
- [ ] Employer can see only eligible Active-Candidate resumes.
- [ ] Unapproved Employer company cannot post a job by form/AJAX/REST; an approved
  owned company can.
- [ ] Matching applications persist once through the authoritative Cariera store;
  nonmatching applications persist nothing.
- [ ] Renewal, upgrade, expiration, refund/suspension and notification isolation
  regressions pass.
- [ ] Cron, Action Scheduler, payment callbacks and password recovery remain usable.

## Deployment and rollback gate

- [ ] Back up plugin files and database; retain the known-good v0.7.3 ZIP.
- [ ] Verify release ZIP root is exactly `click2eu-membership/`, contains no Git,
  test fixtures, backups, credentials or licensed vendor code, and record SHA-256.
- [ ] Deploy to staging first, purge all cache layers and execute this checklist.
- [x] Merge the owner-authorized final v0.7.4 artifact into `develop`.
- [ ] Approve client launch only after zero critical Cariera acceptance failures.
- [ ] For rollback, restore the plugin atomically, purge caches and recheck Join,
  Checkout, My Account, callbacks and scheduled actions. Do not replace the live
  database with the local database.
