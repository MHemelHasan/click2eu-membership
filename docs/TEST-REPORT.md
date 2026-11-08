# Click2EU Membership 0.4.0 Test Report

Dashboard test date: 2026-11-09 (Asia/Dhaka)

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
