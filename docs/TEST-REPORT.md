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
## 0.6.1 executed upgrade regression

The local suite used injected UTC time, temporary `example.invalid` Candidate
users, isolated ledger records, and HPOS WooCommerce CRUD orders/items. It sent no
notification or external payment and removed every fixture, including recovery
cleanup from an interrupted harness run.

| Scenario | Result |
|---|---|
| Basic targets Pro, Premium, Elite | PASS |
| Pro targets Premium, Elite; Premium targets Elite | PASS |
| Same package, downgrade and invalid target rejected | PASS |
| Basic-to-Pro half-term basis €8, credit €4, charge €11 | PASS |
| One-second remaining credit/charge remain bounded | PASS |
| Tax-enabled calculation fails closed | PASS |
| Pending payment preserves original Active access | PASS |
| Completed approval activates target and marks source Upgraded | PASS |
| Target receives full 90-day term from approval | PASS |
| Duplicate completion is idempotent | PASS |
| Source credit is marked consumed once | PASS |
| Source-order reversal suspends target; source is not restored | PASS |
| HPOS enabled and WooCommerce CRUD fixtures | PASS |
| PHP syntax, every plugin PHP file | PASS |

Static review additionally confirmed same-category enforcement, legacy/no-expiry
rejection, pending/scheduled conflict rejection, immutable order metadata, coupon
rejection, zero-charge manual approval, rollback on partial ledger transition,
admin capability enforcement, and existing renewal/expiration code paths.

Not executed: full browser checkout/Block checkout E2E, genuine simultaneous
multi-process approval load, gateway webhooks, tax-inclusive stores, or production
payment/refund behavior. Cariera browser E2E remains BLOCKED pending legitimate
license activation. These limitations prevent a production-ready designation.
## 0.6.2 executed financial-integrity regression

The suite used temporary `example.invalid` Candidates/admin, injected UTC time,
HPOS WooCommerce CRUD orders, real local `wc_create_refund()` refund objects,
isolated ledger rows, and controlled finalization-failure filters. All fixtures,
refunds, schedules, users and rows were removed afterward.

| Scenario | Result |
|---|---|
| Basic → Pro | PASS |
| Basic → Pro → Elite | PASS |
| Pro → Premium → Elite | PASS |
| Original source full refund | PASS |
| Original source partial refund | PASS |
| Intermediate upgrade refund | PASS |
| Current upgrade partial refund | PASS |
| Duplicate refund callback | PASS |
| Refund after source already Upgraded | PASS |
| Missing source order | PASS |
| Cyclic lineage bounded | PASS |
| Cross-user malformed lineage not mutated | PASS |
| Normal renewal unaffected | PASS |
| Existing expiry unaffected | PASS |
| Pending upgrade preserves current access | PASS |
| Duplicate zero-total approval | PASS |
| Interrupted target metadata write recovery | PASS |
| Interrupted schedule update recovery | PASS |
| Zero-total Completed blocked | PASS |
| Explicit administrator zero-total approval | PASS |
| Tax-disabled enforcement | PASS |
| Existing quotation and currency precision | PASS |
| Unrelated refund ignored | PASS |
| HPOS CRUD | PASS |
| Dashboard history rendering | PASS |
| Admin capability/history rendering | PASS |
| PHP syntax for every plugin PHP file | PASS |

Static source review confirmed the 100-node lineage bound, missing-record checks,
positive-charge manual approval path, immutable quote checks, Checkout Block
metadata hooks, and no direct WooCommerce order-table SQL.

**NOT TESTED:** full browser Classic Checkout, browser Checkout Blocks, real BACS
gateway callbacks, or genuine simultaneous multi-process load. **BLOCKED:** Cariera
browser E2E until legitimate local license activation. No production operations
were performed.
## 0.6.3 executed recovery and quote-safety regression

The suite used injected UTC time, temporary `example.invalid` users, HPOS orders,
isolated ledger rows, controlled schedule failure, a target-price filter and more
than 200 recovery candidates. All fixtures, actions, orders, users, rows and the
test cursor were removed or reset after execution.

| Scenario | Result |
|---|---|
| Fresh quote | PASS |
| Checkout after 15-minute quote expiry | PASS |
| Source expires during checkout | PASS |
| Target price changes after quote | PASS |
| 50 finalized rows followed by 51st incomplete row | PASS |
| 51st row eventually recovered | PASS |
| Persistent cursor advances across batches | PASS |
| More than 200 eligible recovery rows | PASS |
| Interrupted schedule finalization recovery | PASS |
| Repeated reconciliation/idempotency | PASS |
| Missing-order recovery rows do not block progress | PASS |
| Long-delayed approval fails closed after material credit decay | PASS |
| Placed order total remains unchanged | PASS |
| Expired cart quote blocks checkout | PASS |
| Existing finalized order remains unchanged | PASS |
| Zero-total Completed remains blocked | PASS |
| Explicit zero-total administrator approval | PASS |
| Duplicate approval | PASS |
| Partial-refund lineage | PASS |
| Normal renewal | PASS |
| HPOS CRUD and scheduled hook registration | PASS |
| PHP syntax for every plugin PHP file | PASS |

**NOT TESTED:** genuine simultaneous refund/activation processes, browser-driven
Classic Checkout, browser-driven Checkout Blocks, real BACS timing, or production
Action Scheduler load. **BLOCKED:** Cariera browser E2E pending legitimate local
license activation. Static review is not counted as an executed browser test.

## 0.7.0 category access-control audit and tests

Baseline audit found that inactive Candidates were blocked from dashboard/resume
pages and heuristic resume/application REST writes, while active Candidates were
correctly not denied. Public reads and unrelated REST routes were not broadly
blocked. The high-severity gap was the absence of category authorization for an
active Candidate across application, resume and REST write paths. v0.7.0 replaces
the route heuristic with resource-aware authorization for the verified scope.

Executed locally with temporary users, ledger rows, job posts and terms. Because
Cariera Addons is inactive, a temporary `resume` post type and `resume_category`
taxonomy matching the inspected vendor schema were registered only inside the test
process. Every database fixture was removed afterward.

| Scenario | Result |
|---|---|
| Guest/Candidate public job browsing | PASS |
| No membership and Pending denied | PASS |
| Pending → Active matching entitlement | PASS |
| Active exact match allowed / nonmatch denied | PASS |
| Expired, Suspended, Cancelled denied | PASS |
| Scheduled renewal not premature | PASS |
| Renewal handoff uses current Active row | PASS |
| Upgraded history denied / current upgrade allowed | PASS |
| Legacy Active policy preserved | PASS |
| Category ID tampering denied | PASS |
| Multi-category one-match policy | PASS |
| Category-less job denied | PASS |
| Parent/child exact-match policy | PASS |
| Deleted purchased category denied | PASS |
| Category label rename does not change entitlement | PASS |
| Explicit resume mapping and owned resume | PASS |
| Unmapped resume category denied | PASS |
| Cross-user resume form/service/REST denied | PASS |
| Application form validation match/nonmatch | PASS |
| Resume form validation match/nonmatch | PASS |
| REST reads available | PASS |
| Candidate REST writes authorized/denied correctly | PASS |
| Unrelated REST write unchanged | PASS |
| Employer and administrator workflows unchanged | PASS |
| Admin category diagnostics | PASS |
| Membership dashboard category | PASS |
| HPOS enabled | PASS |
| Existing pricing cards | PASS |
| PHP syntax, every plugin PHP file | PASS |

Total executed assertions: **39 PASS, 0 FAIL**.

**BLOCKED:** live Cariera application submission, Apply With Resume email/application
creation, resume create/edit handlers, Cariera REST controller interaction, and
Cariera browser E2E because Cariera Addons is inactive and legitimate license
activation is unavailable. **NOT TESTED:** a real mutating Cariera AJAX endpoint;
the installed resume AJAX handler inspected is read-only. Static source inspection
is not reported as an executed integration test.

## 0.7.1 notification audit and tests

The source audit identified ledger writes—not page views or WooCommerce status by
itself—as the authoritative lifecycle boundary. v0.7.1 emits internal events only
after successful Pending creation, activation/scheduling, renewal handoff,
expiration, reversal or upgrade finalization. Administrator events require saved
conflict/review markers. Every queued row is revalidated before transport.

Executed locally with temporary example.invalid users, WooCommerce orders, order
items, ledger rows, categories and job posts. `pre_wp_mail` intercepted every mail
attempt; no external message was sent. All fixtures, notification rows and their
scheduled actions were removed after each run.

| Executed scenario | Result |
|---|---|
| Schema migration and no historical backfill | PASS |
| New Pending membership / deduplicated payment event | PASS |
| Valid activation / duplicate completion callback | PASS |
| Failed activation creates no success event | PASS |
| Scheduled renewal and Active handoff | PASS |
| Scheduled renewal suppresses generic warnings | PASS |
| Finalized upgrade and historical source behavior | PASS |
| Activation conflict / upgrade review / payment lineage alerts | PASS |
| Suspension, cancellation and duplicate reversal | PASS |
| 7-day, 3-day and 1-day exact warning thresholds | PASS |
| Exact UTC expiration and post-expiry warning rejection | PASS |
| Candidate and configured administrator recipient resolution | PASS |
| Transport accepted and transport rejected paths | PASS |
| 5-minute / 30-minute retry backoff and three-attempt limit | PASS |
| Terminal delivery-failure alert | PASS |
| Duplicate event keys and concurrent worker claim | PASS |
| Expired processing-lease recovery runner | PASS |
| Action Scheduler availability and WP-Cron delivery/runner fallback | PASS |
| Missing email and cross-user source isolation | PASS |
| HTML sanitization, identity colors and plain-text rendering | PASS |
| Candidate admin-capability denial and scoped retry nonce | PASS |
| Existing category permission exact match | PASS |
| Notification schema/version idempotency | PASS |

Final notification suite: **55 PASS, 0 FAIL**. The existing v0.7.0 category
suite previously completed **39 PASS, 0 FAIL** and the post-merge hardening check
completed **5 PASS, 0 FAIL**.

A final post-review smoke run completed **6 PASS, 0 FAIL** for Pending-order
validation, intercepted Candidate delivery, stale Completed-order cancellation,
cross-user order rejection, installed queue indexes and schema-version readback.
An additional alert-precedence check completed **3 PASS, 0 FAIL**, confirming that
lineage, upgrade-review and ordinary activation-conflict incidents each emit only
their most specific administrator event.

Static review confirmed prepared notification SQL, escaped administration output,
stable event keys, no arbitrary recipient input, HPOS order CRUD, bounded scans,
retention cleanup, deactivation unscheduling and the lack of message-body storage.

**BLOCKED:** real SMTP/provider acceptance, spam placement and inbox delivery;
production-volume Action Scheduler behavior; Cariera registration/login email
integration; and Cariera browser E2E pending legitimate activation. A true process
crash between mail transport acceptance and the final database update was not
destructively simulated; the lease-recovery behavior surrounding that window was
executed. Successful `wp_mail()` interception is reported only as transport
acceptance, never confirmed inbox delivery.

## 0.7.2 notification reliability hardening

Pre-change isolated reproduction confirmed both findings:

- A failed reminder moved `scheduled_at` by 300 seconds; the next validation
  returned `stale_event` because it compared that retry time with the original
  reminder threshold.
- A row with `status=processing`, `attempt_count=3` and an expired lease was not
  selected by the bounded runner and remained Processing indefinitely.

The final local suite used only example.invalid users and intercepted every
`wp_mail()` call. Temporary users, HPOS orders, memberships, notification rows and
scheduled test actions were removed afterward.

| Executed scenario | Result |
|---|---|
| 7-day first failure and successful second attempt | PASS |
| 3-day two failures and successful third attempt | PASS |
| 1-day first failure and successful second attempt | PASS |
| Immutable due time across retry backoff | PASS |
| Accepted reminder cannot be delivered twice | PASS |
| Expiration before retry | PASS |
| Scheduled renewal before retry | PASS |
| Upgrade before retry | PASS |
| Refund/suspension before retry | PASS |
| v0.7.1 retry-row original-time backfill | PASS |
| Accepted-history migration preservation | PASS |
| First-claim lease crash recovery | PASS |
| Second-claim lease crash recovery | PASS |
| Final-claim lease → Uncertain without Candidate resend | PASS |
| Deduplicated uncertainty administrator alert | PASS |
| Duplicate runner and competing worker | PASS |
| Uncertain outcome retention | PASS |
| Normal third rejected attempt → Failed | PASS |
| Previously Accepted row unchanged | PASS |
| All Candidate lifecycle event regressions | PASS |
| Activation conflict, upgrade review and lineage alerts | PASS |
| Candidate ownership and cross-user isolation | PASS |
| Action Scheduler and WP-Cron fallback | PASS |
| HTML and plain-text templates | PASS |
| Accepted-row cleanup / Uncertain preservation | PASS |
| Admin Uncertain diagnostics, capability and retry nonce | PASS |
| WooCommerce HPOS | PASS |

Final targeted and regression suite: **47 PASS, 0 FAIL**. PHP 8.2 and PHP
8.4 syntax checks are executed separately during release validation.

Post-migration uncertainty smoke suite: **7 PASS, 0 FAIL**. This verified the
installed 1.1.0 schema, preserved transport evidence, separate review notes and
deduplicated administrator escalation.

**BLOCKED / NOT TESTED:** real SMTP acceptance, provider logs, inbox placement,
and a destructive operating-system process kill during the post-transport update
window. The equivalent expired-lease database states were executed deterministically.
Cariera browser E2E remains BLOCKED pending legitimate activation.

## 0.8.0-A independent browser and integration QA / 0.7.3 fix

Real Chrome browser automation exercised the local Join Us page, Classic Checkout,
WooCommerce HPOS order administration, Candidate My Account membership UI, all
four manual approvals, three upgrade paths, renewal, public job browsing and both
Click2EU administrator screens. Outgoing mail was intercepted and all users,
orders, terms, ledger rows, notification rows and scheduled fixture actions were
removed after testing.

One HIGH defect was confirmed: `wp_kses_post()` removed renewal and upgrade forms
from `Click2EU_Dashboard::account_content()`. Version 0.7.3 uses a narrow additional
allowlist and retains sanitization. Real-browser regression completed Basic to Pro,
Basic to Premium and Pro to Elite upgrades with correct credits and approval state.

The valid integration run completed **62 PASS, 0 product FAIL**. Two assertions in
the first retry harness used an upgrade-target membership for a generic activation
event; validation correctly rejected that impossible source. Those harness results
were discarded, the fixture was corrected to an original Active membership, and
all three retry assertions passed. Action Scheduler executed with no due work, and
an isolated WP-Cron fallback check scheduled the five-minute runner and daily
cleanup at their exact delays.

See `BROWSER-E2E-REPORT.md`, `INTEGRATION-MATRIX.md` and `QA-BLOCKERS.md` for the
evidence classification and remaining limitations.
