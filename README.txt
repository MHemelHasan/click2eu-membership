Click2EU Membership (v0.7.2)
=======================================

v0.7.2 NOTIFICATION RELIABILITY HARDENING:
- Separates immutable notification event due time from mutable retry execution
  time, so 7/3/1-day expiry reminders remain valid after transport retries.
- Safely derives original reminder due times for existing v0.7.1 queue rows while
  preserving accepted status, attempt history and transport timestamps.
- Expiry retry validation checks deterministic identity, original due time,
  current UTC time, membership state, effective expiry and Scheduled renewal.
- Recovers an expired third-attempt processing lease into an Uncertain state. The
  Candidate message is not automatically resent because transport acceptance is
  unknown; a deduplicated administrator alert is generated instead.
- Uncertain rows remain visible in diagnostics and are excluded from automatic
  retention cleanup until an administrator reviews the outcome.

v0.7.1 MEMBERSHIP NOTIFICATIONS:
- Adds a persistent, UTC notification ledger with deterministic event keys.
- Candidate events cover Pending payment, activation, scheduled/started renewal,
  completed upgrade, 7/3/1-day expiry warnings, expiration, suspension and
  cancellation.
- Administrator alerts cover activation conflicts, upgrade review, invalid
  payment lineage and terminal notification transport failure.
- Action Scheduler is preferred; WP-Cron is the fallback. A bounded five-minute
  runner recovers expired processing leases without scanning frontend requests.
- Delivery is attempted with wp_mail() and remains compatible with separately
  configured SMTP plugins. "Accepted" means only that the WordPress transport
  accepted the message; it does not confirm inbox delivery.
- Failed transport attempts retry after 5 minutes, 30 minutes and 2 hours, with
  three total attempts. Stable unique keys suppress duplicate lifecycle emails.
- Lightweight responsive HTML and plain-text rendering use the Click2EU visual
  identity without external fonts or stored message bodies.
- Existing membership rows are not backfilled automatically during upgrade.

v0.6.0 EARLY RENEWAL AND ADMIN MANAGEMENT:
- Active dated memberships may renew only the same package and job category.
- Renewal checkout and manual payment review do not alter the current term.
- Approved early renewals become Scheduled and start at the current expiry.
- Scheduled renewals receive their complete purchased term with no overlap,
  proration, discount, unused-time refund or category change.
- Access-time reconciliation starts a paid due renewal even when cron is late.
- Failed/refunded scheduled renewals are cancelled; refunds after activation
  suspend the renewed membership without rewriting earlier history.
- Legacy Active memberships without expiry cannot renew automatically.
- Adds an Administrator-only, read-only Click2EU Memberships lifecycle page.
- Mid-term upgrades remain unavailable and are never converted into renewals.

v0.5.0 DURATION AND EXPIRATION ENGINE:
- Trusted server-side package terms: Basic 30, Pro 90, Premium 180 and Elite
  365 elapsed 24-hour days. Values are filterable for future administration.
- Each new order item and ledger row snapshots its purchased duration; later
  configuration changes do not alter an existing term.
- Duration starts only when a validated Candidate order becomes Completed.
- UTC activation and expiration timestamps are stored in the membership ledger.
- Action Scheduler receives a unique expiry action when available; WP-Cron is
  the fallback, with hourly reconciliation for delayed or missing events.
- Entitlement checks compare the real UTC expiry, so delayed cron never extends
  Candidate privileges.
- Expired Candidates retain account/history access and may purchase a new plan.
- Existing Active rows without an expiration remain legacy Active and display
  "Expiration not configured" until a future explicit migration policy.
- No automatic renewal, mid-term upgrade or unused-time carryover is included.

v0.4.1 STABILITY PATCH:
- Current membership selection is Active first, otherwise the newest Pending,
  otherwise the newest historical record regardless of terminal status.
- Completing a second membership order no longer suspends or replaces a
  different Active membership.
- Conflicting completed orders remain Pending for administrator review and
  receive internal conflict metadata plus an order note.
- Candidate views label such orders "Review required" instead of incorrectly
  saying that payment is still awaiting verification.
- No renewal, upgrade, expiry or new ledger status is introduced.

v0.4.0 MEMBERSHIP DASHBOARD:
- Adds the WooCommerce My Account endpoint /my-account/my-membership/.
- Adds a Candidate-only "My Membership" account menu item.
- Adds [click2eu_membership_dashboard] for the same read-only dashboard on
  other authenticated pages.
- Shows the repository-selected current record, payment/order status, package,
  category, activation date and an ownership-checked WooCommerce order link.
- Shows newest-first ledger history with ten records per page, including
  pending, active, suspended and cancelled records and missing-order handling.
- Adds no expiry, renewal, status mutation or duplicate history table.

PHASE 2 UPDATE:
- Adds a persistent Candidate membership ledger.
- Creates pending records for recognized membership orders.
- Activates only when an administrator moves the order to Completed.
- Suspends access after Failed, Cancelled or Refunded reversals.
- Adds Candidate role/cart validation, protected feature checks, Cariera login
  routing and [click2eu_membership_status].
- v0.5.0 activates and enforces the snapshotted duration for new approvals.

v0.3.1 hardens one-package-per-order enforcement, revalidates active/pending
membership state at checkout, serializes per-Candidate activation, and records
pre-activation order reversals as cancelled rather than pending.

See docs/DEPLOYMENT.md and docs/TEST-REPORT.md for the current architecture,
configuration, test evidence and rollback procedure.

MEMBERSHIP DASHBOARD SHORTCODES:
  [click2eu_membership_dashboard]
      Full current-membership card and paginated membership history. The
      viewer must be signed in as the Candidate who owns the records.

  [click2eu_membership_status]
      Existing compact membership status output; retained unchanged.

UPDATE: redesigned from the user-provided Click2EU homepage HTML PLANS section.
The plugin retains the v0.1.0 WooCommerce checkout behavior and original folder/slug.

PRODUCT IDS (already mapped):
  Basic = 11868 / Pro = 11869 / Premium = 11870 / Elite = 11871

INSTALL / UPGRADE:
1. BACK UP your site first; test on staging.
2. WP Admin > Plugins > Add New > Upload Plugin.
3. Upload this ZIP. If WordPress detects a prior Click2EU Membership plugin,
   click 'Replace current with uploaded'. Activate it if required.
4. Clear any page/cache plugin and browser cache so v0.2.0 CSS appears.
5. Edit your Elementor page and place a Shortcode widget.

SHORTCODES (THE THREE PRESENTATION MODES):
  [click2eu_packages_section]
      Full HTML-inspired section: small PLANS label, main heading, subtitle,
      four cards, disclaimer underneath.

  [click2eu_packages]
      Four cards ONLY (no heading/subheading/disclaimer).

  [click2eu_package package="basic"]
  [click2eu_package package="pro"]
  [click2eu_package package="premium"]
  [click2eu_package package="elite"]
      ONE complete card, including category dropdown and Join button.

  [click2eu_join package="basic"]
      Legacy shortcode: dropdown + Join button ONLY for your OWN Elementor card.
      'pro', 'premium', 'elite' also work. This has NOT been removed.

  [click2eu_packages section="yes"]
      Alias for full section if you want to use the existing shortcode name.

OPTIONAL SHORTCODE ATTRIBUTES:
  show_duration="yes"  (default yes; 30/90/180/365 days on front end)
  show_day_rate="yes"  (default yes; approximate price/day from live WC price)
  show_duration="no"   (hide duration and per-day line entirely)

  Full section text can be overridden, e.g.:
  [click2eu_packages_section subtitle="One payment, no auto-renewal. Pay manually at checkout."]
  It also accepts eyebrow="", title="", disclaimer="", id="plans".

DISPLAY COPY / FEATURES:
- Colors, spacing, typography, four columns and Elite badge follow the
  provided Click2EU reference HTML screenshot (no Tailwind dependency).
- Product price is read from WooCommerce (not hard-coded); whole amounts
  visually omit trailing .00, like the provided screenshot.
- Each product Short Description takes PRIORITY for the feature list.
- If a product's Short Description is EMPTY, the copy from the uploaded
  Click2EU homepage HTML is used as its fallback feature list.
- The default subtitle from the HTML says 'Pay by bank transfer'. If you use
  Check Payments or COD instead, change subtitle via the shortcode as above.
- Claims about profile views, prioritization, support, video introductions
  are UI copy only; these features are NOT enabled by this Phase 1 plugin.
  Edit Product Short Descriptions to avoid promising unavailable features.

CATEGORY AND CHECKOUT (UNCHANGED FROM v0.1.0):
- WP Job Manager taxonomy 'job_listing_category' feeds the dropdown.
- Every card shows required dropdown directly above the full-width Join button.
- Join requires sign-in, validates nonce/category/product server-side, then adds
  the selected product (qty 1), removes other cart items and redirects to the
  CLEAN WooCommerce checkout URL (no ?add-to-cart or product ID).
- Checkout displays the chosen Job Category and package feature copy.
- WooCommerce order items and order meta store the category / package.
- Supports WooCommerce Checkout Block and classic checkout item metadata.
- Manual gateways must be enabled via WooCommerce Payments; COD for virtual
  products requires 'Accept for virtual orders'.

MEMBERSHIP DURATION
  30/90/180/365 days are provisional server-side terms. A new ledger record
  snapshots its duration, but its clock starts only after verified payment and
  the WooCommerce order transition to Completed. Expiration removes protected
  Candidate privileges while retaining account, order and membership history.
  No automatic renewal is performed.

TEST CHECKLIST:
[ ] Existing four product IDs still load their live prices.
[ ] Section shortcode displays header, 4 cards and disclaimer.
[ ] Cards-only shortcode renders no heading or disclaimer.
[ ] Each single-card shortcode renders exactly one full card.
[ ] Existing click2eu_join shortcodes still work.
[ ] Dropdown sits directly above Join button and requires a category.
[ ] Category from WP Job Manager shows in checkout & order details.
[ ] Checkout URL has no add-to-cart or category parameters.
[ ] Small screen: cards stack vertically and do not overflow.
[ ] Offline manual payment order completes without payment activation promises.

Safety: PHP lint passed at packaging time. The ZIP was not installed on your live
WordPress/Cariera site; verify the flow on staging before going live.
Click2EU Membership 0.6.1
=========================

Version 0.6.1 adds secure mid-term upgrades from Basic to Pro/Premium/Elite,
Pro to Premium/Elite, and Premium to Elite in the same job category. A trusted
server quote credits the unrefunded, post-discount net source line in proportion
to its remaining seconds. Checkout stores the frozen inputs in WooCommerce order
metadata and never accepts a browser-supplied price.

The automated calculation is deliberately available only when WooCommerce taxes
are disabled, both orders use the store currency, and every refund is attributable
to the source membership line. Coupons cannot be applied to an upgrade order.
Completed remains the sole manual-payment approval trigger, including a zero-total
upgrade. Ambiguous financial cases are blocked for administrator review.

An approved upgrade marks the previous ledger row Upgraded, activates the target
for its full new duration, preserves the previous expiry for audit, and consumes
the source credit once. Refunds never automatically restore the previous term or
issue cash for unused credit.
Click2EU Membership 0.6.2
=========================

Version 0.6.2 is a focused financial-integrity patch. Refund processing now
traverses the complete same-Candidate upgrade lineage with cycle detection and a
100-node safety bound. Any active membership whose payment ancestry becomes
invalid is suspended; pending/scheduled descendants are cancelled. Historical
Upgraded records remain intact and no previous membership is restored.

Both full and partial WooCommerce refund hooks are handled idempotently. Refund
fingerprints prevent overlapping callbacks from duplicating audit work, while
unrelated orders and cross-user malformed lineage are never mutated.

Upgrade finalization is recoverable after the custom-ledger transaction commits.
Source-credit consumption, expiration scheduling, target activation metadata and
notes are individually idempotent and reconciled on a later request without
requiring another order-status transition. Recovery revalidates the source payment.

Zero-total upgrades require the WooCommerce order action "Approve zero-total
Click2EU upgrade" from an administrator with WooCommerce management capability.
Completed status alone never activates a zero-total upgrade.
Click2EU Membership 0.6.3
=========================

Version 0.6.3 prevents recovery starvation and stale unused-time credits. Upgrade
recovery no longer scans the first 50 rows on every frontend request. Action
Scheduler runs a 25-row batch every five minutes (WP-Cron fallback), saving a
persistent membership-ID cursor after each row. Completed history cannot starve a
later interrupted upgrade, and a process interruption safely repeats at most the
current idempotent row.

Upgrade quotes are valid for 15 minutes by default. The duration is configurable
with `click2eu_upgrade_quote_ttl`. Classic and Block cart validation reject an
expired or changed quote; order metadata retains an additional fail-closed review
marker if order creation bypasses normal validation. Placed orders are never
silently repriced.

At manual approval the source payment, expiry, target price, placement-time quote
validity and current unused-time credit are revalidated. If the frozen credit is
more favorable by over one currency cent (configurable through
`click2eu_upgrade_approval_credit_tolerance`), activation is blocked for review.
No extra charge or automatic refund is created.

Click2EU Membership 0.7.0
=========================

Version 0.7.0 adds server-side Candidate category entitlements without changing
the payment ledger. The purchased `category_term_id` in the effective Active
membership is the sole job-category authorization key. Labels, submitted IDs,
profile metadata, URLs and JavaScript are never trusted.

Public job browsing remains open. Protected applications require an authenticated
Candidate, an expiration-aware Active membership and at least one exact
`job_listing_category` term match. Category-less jobs are denied for applications;
parent/child terms do not imply access. Employers and administrators retain their
existing workflows.

Cariera resumes use the separate `resume_category` taxonomy. Configure an explicit
job-to-resume mapping in the `click2eu_resume_category_map` option or the filter of
the same name, for example `array( 100 => array( 250 ) )`. IDs and names are never
assumed interchangeable. Missing taxonomy, terms or mappings fail closed only for
protected resume actions.

Verified adapters cover Cariera's `application_form_validate_fields` and
`submit_resume_form_validate_fields`, direct application/resume POST names, WP Job
Manager's per-job apply visibility filter, and standard `/wp/v2/resumes` writes.
Unrelated REST routes and public REST reads are unchanged. Cariera Addons is
inactive locally, so live application/resume E2E remains blocked.
