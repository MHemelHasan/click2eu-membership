# Click2EU Membership 0.6.0 Test Report

Renewal test date: 2026-11-09 (Asia/Dhaka)

## 0.6.0 executed renewal/admin regression

Tests used an injected UTC clock, temporary `example.invalid` Candidates,
isolated ledger records and local HPOS WooCommerce CRUD orders/items. No imported
record, production system, payment service or real recipient was used. Fixtures
and scheduled actions were removed after execution.

| Scenario | Result |
|---|---|
| Schema-v3 renewal fields and version | PASS |
| Active Candidate creates same-plan/category Pending renewal | PASS |
| Pending renewal preserves current access and remaining days | PASS |
| Early approval schedules start at existing expiry | PASS |
| Scheduled renewal receives full purchased duration | PASS |
| Early payment does not start new term | PASS |
| Approval marker does not falsely claim activation | PASS |
| Duplicate approval is idempotent | PASS |
| Unique scheduled-start action exists | PASS |
| Delayed cron causes no entitlement gap | PASS |
| Old term expires and new term starts at boundary | PASS |
| Historical rows remain intact | PASS |
| Refund before start cancels renewal and preserves Active | PASS |
| Refund after start suspends renewed membership | PASS |
| Invalid ownership is rejected | PASS |
| Concurrent renewal approvals cannot overlap | PASS |
| Different-package upgrade remains blocked | PASS |
| Legacy Active receives no invented expiry or renewal | PASS |
| Candidate dashboard exposes renewal lifecycle | PASS |
| Checkout renewal metadata preserved | PASS |
| HPOS WooCommerce CRUD | PASS |
| Admin page requires `manage_options` and registers | PASS |
| Post-review renewal smoke, schema and admin stylesheet | PASS |
| PHP syntax, every plugin PHP file | PASS |

Admin page rendering was exercised through registration and capability checks;
interactive browser filter/navigation testing is **NOT TESTED**. Genuine
simultaneous multi-process load was not generated, though competing approvals
were executed through the same locked repository path.

## 0.5.0 executed expiration regression

The suite used an injected UTC clock, temporary `example.invalid` Candidates,
local WooCommerce CRUD orders/items and isolated ledger rows. It did not change
the operating-system clock, imported customers/orders, payment gateways or real
email recipients. All test users, orders, rows and scheduled expiry actions were
removed in `finally` cleanup.

| Scenario | Result |
|---|---|
| Schema-v2 columns and version verification | PASS |
| Existing unique order-item constraint preserved | PASS |
| Basic activation snapshots and enforces 30 days | PASS |
| Pro activation snapshots and enforces 90 days | PASS |
| Premium activation snapshots and enforces 180 days | PASS |
| Elite activation snapshots and enforces 365 days | PASS |
| Pending and On-hold do not start duration | PASS |
| Completed starts duration exactly once | PASS |
| One second before expiry allows access | PASS |
| Exact expiry timestamp denies access | PASS |
| One second after expiry denies access | PASS |
| Delayed cron cannot retain access | PASS |
| Repeated expiration is idempotent | PASS |
| Scheduled expiry hook performs Active to Expired | PASS |
| Legacy Active without expiry remains Active | PASS |
| Suspended and Cancelled audit states are preserved | PASS |
| Expired then refunded records stronger Suspended audit state | PASS |
| Expired Candidate creates a new Pending purchase | PASS |
| New Pending grants no access | PASS |
| New Completed approval activates repurchase | PASS |
| Historical expired row and expiry remain unchanged | PASS |
| Active Candidate remains blocked from another purchase | PASS |
| v0.4.1 conflict-review behavior remains intact | PASS |
| Dashboard shows expiry, remaining days and history expiry | PASS |
| Status shortcode shows consistent expiry details | PASS |
| UTC boundary and Asia/Dhaka conversion | PASS |
| WP Job Manager category snapshot preserved | PASS |
| Checkout category and duration metadata preserved | PASS |
| HPOS enabled and WooCommerce CRUD succeeds | PASS |
| Unique future expiration action detected | PASS |
| PHP syntax, every plugin PHP file | PASS |

Action Scheduler was available locally and used for individual expiration. The
WP-Cron fallback and hourly reconciliation path were verified by source review;
the fallback was not forced while Action Scheduler was active.

Cariera registration/login and protected-feature browser E2E remain **BLOCKED**
until legitimate local license activation. Full browser checkout submission and
real cron timing under production traffic are **NOT TESTED**.

## 0.4.1 executed stability regression

The test used isolated `example.invalid` Candidate accounts, local WooCommerce
orders and temporary ledger records. No real order status transition,
notification, payment or external request occurred. Every fixture was removed
after execution.

| Scenario | Result |
|---|---|
| No membership | PASS |
| Pending membership | PASS |
| Active membership | PASS |
| Suspended-only history | PASS |
| Cancelled-only history | PASS |
| Older Suspended plus newer Cancelled selects Cancelled | PASS |
| Older Cancelled plus newer Suspended selects Suspended | PASS |
| Active plus multiple newer historical records selects Active | PASS |
| Multiple Pending/history records select newest Pending | PASS |
| One Pending order completes and activates normally | PASS |
| Repeated completion does not duplicate notes or activation | PASS |
| Second distinct completed order does not replace Active | PASS |
| Conflict metadata records retained Active membership | PASS |
| Repeated conflict handling is idempotent | PASS |
| Serialized competing activation through advisory-lock path | PASS |
| Refund/cancellation path suspends Active membership | PASS |
| Status shortcode and dashboard agree on terminal history | PASS |
| Dashboard labels completed conflict for review | PASS |
| Both conflicting history records remain visible | PASS |
| History pagination | PASS |
| Existing pricing cards | PASS |
| Existing checkout line metadata | PASS |
| HPOS enabled and WooCommerce CRUD order access | PASS |
| PHP syntax, every plugin PHP file | PASS |

The competing activation test exercised the same serialized repository path
used under the MySQL advisory lock. A truly simultaneous multi-process race was
not generated; the lock acquisition/recheck/release implementation was also
verified by static source inspection.

## 0.4.0 dashboard integration tests

## 0.4.0 dashboard integration tests

Tests used temporary `example.invalid` Candidate/Employer users, local
WooCommerce orders and isolated membership-ledger rows. The fixtures included
pending, active, suspended, cancelled, multiple-history, missing-order and
cross-user-order cases. All temporary users, orders and ledger rows were removed
after the run; no notification or external payment request was sent.

| Test | Result |
|---|---|
| Guest dashboard requires authentication | PASS |
| Employer cannot view Candidate records or Candidate menu item | PASS |
| Candidate with no membership sees empty state and Join Us | PASS |
| Pending state shows payment-verification copy and My Orders | PASS |
| Active state shows activation data and owned order link | PASS |
| Suspended state is clearly identified | PASS |
| Cancelled record is not presented as active | PASS |
| Multiple records are newest-first and paginated at ten rows | PASS |
| Missing historical order is represented as unavailable | PASS |
| Cross-user WooCommerce order link is suppressed | PASS |
| Selected job category renders | PASS |
| Candidate My Account menu item and endpoint callback register | PASS |
| `[click2eu_membership_dashboard]` registers and renders | PASS |
| Existing `[click2eu_membership_status]` remains registered | PASS |
| Existing pricing shortcode still renders | PASS |
| Ledger count/page queries remain user-scoped | PASS |
| Current record follows repository status precedence | PASS |
| PHP syntax for every plugin PHP file | PASS |
| HPOS-safe order reads use WooCommerce CRUD | PASS |
| Mobile CSS uses a scoped stacked table/card layout | PASS (structural) |

No schema migration was introduced. Checkout creation and activation classes
were not modified by the dashboard implementation.

## Prior v0.3.1 engine tests

The integration tests used two temporary `example.invalid` users and one local
WooCommerce order. No real payment gateway, notification or external webhook
was invoked. The users, order and ledger row were removed after the run.

| Test | Result |
|---|---|
| Guest direct membership add blocked | PASS |
| Employer Candidate package blocked | PASS |
| Direct add without package/category blocked | PASS |
| Valid Candidate selection accepted | PASS |
| Recognized order creates pending ledger record | PASS |
| Pending and On-hold grant no access | PASS |
| Completed transition activates membership | PASS |
| Activation marker saved | PASS |
| Repeated completion is idempotent | PASS |
| Status shortcode shows current Candidate record | PASS |
| External login redirect rejected | PASS |
| Safe local account redirect preserved | PASS |
| Unauthorized resume REST write blocked with 403 | PASS |
| Public resume REST read remains available | PASS |
| Registration-only Candidate has no membership | PASS |
| Active Candidate cannot create a second package order | PASS |
| Active membership lifts Click2EU REST restriction | PASS |
| Pending cancellation is recorded as cancelled | PASS |
| Crafted multi-package order creates no ledger | PASS |
| Per-Candidate activation advisory lock acquires/releases | PASS |
| Status display: none, pending, active and suspended | PASS |
| Status display prevents cross-user record exposure | PASS |
| Refund suspends without deleting history | PASS |
| Suspended order does not silently reactivate | PASS |
| Existing four-card pricing shortcode renders | PASS |
| PHP syntax, all plugin PHP files | PASS |
| HPOS enabled and WooCommerce CRUD used | PASS |
| Custom pricing CSS unchanged | PASS |

## HTTP and end-to-end status

| Scenario | Result | Note |
|---|---|---|
| Join Us public response and pricing cards | PASS | HTTP 200 |
| Checkout endpoint resolves | PASS | Empty cart redirects to Cart |
| My Account endpoint resolves | PASS | HTTP 200 |
| Cariera registration/login rendering | BLOCKED | Core self-deactivates without the local license activation flag |
| Candidate Dashboard rendering | BLOCKED | Cariera Addons self-deactivates for the same reason |
| Full browser registration/login flow | BLOCKED | Required Cariera runtime unavailable |
| Browser BACS/cheque submission | BLOCKED | Both browser automation runtimes failed; fallback headless process was rejected before launch |
| Homepage visual browser test | NOT TESTED | Scripted request timed out on the Elementor-heavy page |

## Cariera license diagnosis

Cariera binds the license to `site_url()` and self-deactivates Core/Addons when
the license activation option is false. The vendor states that one license is
valid for one WordPress installation and must be deactivated on the previous
installation before activation on another. Production was not touched and the
local activation endpoint was not called. The supported remedies are a separate
license, vendor-confirmed local/development allowance, or an official reset after
coordinating the production license.

Historical logs show prior PHP 8.2 requests failed Contact Form 7's PHP >=8.3
requirement. The current LocalWP web process is PHP 8.4 and should remain there.
