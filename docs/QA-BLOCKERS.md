# QA Blockers and Remaining Limits

## v0.7.4 Phase-1 launch decision: NO-GO

The access-control implementation passed source, isolated WordPress, local HTTP
and real Chrome routing/Classic Checkout checks. Critical real Cariera-handler
evidence remains unavailable. The plugin remains version 0.7.3 on the feature
branch. No v0.7.4 ZIP or `develop` merge is authorized by the current evidence.

Authenticated live diagnosis is also blocked. A disposable Chrome profile could
reach the public host only through its password-protection redirect, and no live
password, Candidate cookie, order key or account credential was copied or used.
The reported live `ERR_TOO_MANY_REDIRECTS` return leg therefore remains unknown.
The proven v0.7.3 contribution was Pending Join-to-Orders plus Dashboard-to-Join,
which produced Dashboard-to-Join-to-Orders locally but terminated at Orders.

### Executable authenticated live verification

1. Use a new isolated Candidate and one Basic Cheque/BACS order; do not use an
   imported customer or existing commercial order.
2. In Chrome DevTools Network, enable **Preserve log** and **Disable cache**.
3. Visit Homepage, Join, Candidate Dashboard, My Profile, My Orders, My
   Membership, Checkout and the new Order Received URL independently.
4. For every 3xx response record Request URL, status, redacted `Location` and
   `X-Redirect-By`. Stop at the first URL repeated in the chain.
5. Export a sanitized HAR after removing cookies, authorization headers, order
   keys, nonces, emails and personal fields.
6. Read back the isolated order with WooCommerce CRUD/WP-CLI and confirm payment
   method, `on-hold` status and exactly one Pending membership row.
7. Repeat only after deploying the candidate to staging with legitimately active
   Cariera Core/Addons; never perform this acceptance directly on production.

### Required owner-run read-only diagnostics

Run these on staging/live from the WordPress root and return the output. Do not
include license keys, environment files or customer/order data.

```sh
wp core version
wp --info
wp plugin get woocommerce --fields=name,status,version
wp plugin get wp-job-manager --fields=name,status,version
wp plugin get cariera-plugin --fields=name,status,version
wp plugin get cariera-addons --fields=name,status,version
wp role list --fields=role,name
wp option get woocommerce_custom_orders_table_enabled
wp option get click2eu_membership_db_version
wp option get click2eu_notification_db_version
wp option get click2eu_resume_category_map --format=json
wp post-type list --fields=name,public,show_in_rest
wp taxonomy list --fields=name,object_type,public,show_in_rest
wp cron event list --fields=hook,next_run_gmt,recurrence
```

Also capture the Click2EU Settings diagnostics screen after page mappings are
configured, with personal information and URLs containing secrets redacted.

### Mandatory staging evidence before release

- Real Candidate/Employer registration and both login redirect paths.
- Real resume create/edit, Pending Review persistence, admin approval and
  Employer visibility, including hide-on-expiry/suspension.
- Every active application method, CV upload and Apply With Resume, with the
  authoritative application record checked after submission.
- Unapproved company job-post denial and approved company success through form,
  AJAX and REST paths actually enabled on staging.
- Direct Message denial before insert/upload/email for unpaid Candidates.
- Browser console/network, no redirect loops, checkout/payment callbacks,
  HPOS records, renewal/upgrade, cron and Action Scheduler.
- Cache/CDN bypass verification with separate guest, unpaid and Active sessions.

## BLOCKED — Cariera-dependent browser workflows

Cariera Core/Addons cannot remain active in this local environment without a
legitimate vendor activation. No license bypass or activation attempt was made.
The following remain BLOCKED:

- Cariera Candidate registration and login
- Real Resume Manager create/edit
- Real Apply With Resume
- Employer application management
- Actual Cariera Addons integration

Independent WordPress, WooCommerce, membership, category-policy REST protection
and public-job behavior were tested without claiming these vendor workflows.

## NOT TESTED

- Checkout Block / Store API checkout: the site uses Classic Checkout only.
- Real SMTP acceptance, provider logs, spam placement and inbox delivery: all
  `wp_mail()` calls were intercepted with example.invalid recipients.
- A browser session changing users after checkout initialization.
- A zero-total upgrade browser approval fixture.
- Distinct-process concurrency/load testing.
- Production-scale Action Scheduler throughput.
- A destructive process kill in the narrow post-mail-transport update window.

## Production blockers

Version 0.7.3 is suitable for continued v0.8.0-B development, but production
release remains blocked on legitimate Cariera activation and its full browser E2E,
staging SMTP/provider verification, Checkout Block testing if the production page
uses that block, and normal staging acceptance of the 0.7.3 dashboard form fix.

The LocalWP optional Imagick extension warning should be corrected for a clean
local PHP startup, although it did not affect membership QA. The generic WordPress
Admin heartbeat notice seen in headless Chrome should be rechecked in an interactive
staging browser; order writes themselves completed and persisted successfully.
