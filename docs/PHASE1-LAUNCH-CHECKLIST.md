# Phase-1 Launch Configuration and Acceptance Checklist

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
- [ ] Approve `develop` merge and v0.7.4 release only after zero critical failures.
- [ ] For rollback, restore the plugin atomically, purge caches and recheck Join,
  Checkout, My Account, callbacks and scheduled actions. Do not replace the live
  database with the local database.
