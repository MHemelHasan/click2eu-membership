# Click2EU Membership 0.6.0

## 0.6.0 early renewal and administration

### Schema version 3.0.0

The idempotent `dbDelta()` migration adds without replacing existing rows:

- `renewal_of_membership_id bigint(20) unsigned NULL`
- `renewal_type varchar(20) NOT NULL DEFAULT ''`
- `starts_at datetime NULL`
- `KEY renewal_parent (renewal_of_membership_id)`
- `KEY candidate_starts (candidate_user_id, starts_at)`

The version option is updated only after all renewal and expiration fields are
verified. One WooCommerce order item still maps to one ledger row.

### Renewal lifecycle

1. A dated Active Candidate opens the dashboard Renew action.
2. Checkout accepts only the same package and category and snapshots the parent
   membership ID, `early` renewal type and full duration.
3. The new ledger row remains Pending while manual payment is reviewed. The
   existing Active row and expiry are unchanged.
4. Completed approval changes the renewal to Scheduled, with `starts_at` equal
   to the current expiry and a full new `expires_at` after it.
5. `click2eu_start_membership` starts the renewal. If cron is delayed, an
   entitlement lookup performs the same locked expiry/start handoff, preventing
   an access gap.
6. Approval and start transitions are idempotent. Candidate advisory locks and
   scheduled-row checks prevent overlapping effective Active memberships.

If approval occurs after the old term has already expired, the renewal starts at
approval time rather than granting unpaid backdated access. A failed, cancelled
or refunded scheduled renewal becomes Cancelled and leaves the old Active term
unchanged. A refund after renewal activation makes that renewal Suspended.

Legacy Active memberships with no expiry cannot enter early renewal because no
safe future start boundary exists. No date is invented.

### Administrator page

**WP Admin > Click2EU Memberships** requires `manage_options`. It is read-only
and shows Candidate, package/category, HPOS-safe order link/status, membership
status, activation/start/expiry dates, renewal parent/children and activation
conflicts. Filters cover Active, Pending, Scheduled, Expired, Suspended and
Review Required. Payment approval remains exclusively in WooCommerce Orders;
there is no Mark Paid or direct status control.

### Upgrade boundary

Different-package or different-category attempts remain blocked with an explicit
upgrade-unavailable message. v0.6.0 implements no proration, price adjustment,
unused-time refund, mid-term upgrade or automatic conflict resolution.

## 0.5.0 duration and expiration engine

### Duration rules and UTC storage

- Basic: 30 days; Pro: 90 days; Premium: 180 days; Elite: 365 days.
- Terms are provisional and centralized in `Click2EU_Config::durations()` with
  the `click2eu_membership_durations` filter for future administration.
- Checkout stores `_click2eu_duration_days` on the order item and order. The
  ledger stores its own immutable purchased snapshot.
- A term starts only after the associated Candidate order is validated as
  Completed. One day always means 86,400 elapsed seconds.
- `activated_at`, `expires_at` and `expired_at` are UTC MySQL datetimes. UI
  formatting uses the configured WordPress timezone.

### Schema migration

Schema version `2.0.0` adds these columns without truncating or recreating the
table:

- `duration_days smallint(5) unsigned NOT NULL DEFAULT 0`
- `expires_at datetime NULL`
- `expired_at datetime NULL`

It also adds `KEY status_expires (status, expires_at)`. `dbDelta()` preserves
the existing primary key, unique `order_item_id`, rows and history. The schema
version option is updated only after all three new columns are verified.

### Expiration scheduling and access

Activation schedules one `click2eu_expire_membership` action keyed by membership
ID. Action Scheduler is preferred when initialized; otherwise a single WP-Cron
event is used. An hourly `click2eu_reconcile_memberships` WP-Cron event repairs
delayed/missing schedules and schedules future rows without duplicates.

WP-Cron can be late on low-traffic sites. Every effective entitlement lookup
therefore compares the current UTC timestamp with `expires_at` and reconciles a
due Active row under the per-Candidate advisory lock. At the exact expiration
timestamp access is denied, even if no scheduled action has run.

Expired Candidates keep My Account, My Profile, My Orders, Membership History,
Join Us and public job browsing. Protected Candidate dashboard, resume,
application, premium AJAX and premium REST writes require a still-effective
Active membership.

### Legacy and repurchase policy

Existing Active rows whose `expires_at` is NULL are deliberately treated as
legacy Active. They are not backdated or automatically expired. The dashboard
shows **Expiration not configured**. A separate approved migration policy is
required before assigning legacy terms.

After a dated membership expires, the old row/order remains unchanged and the
Candidate may create a new Pending purchase. The new term starts only after its
own Completed approval. No old order is reactivated, no unused time is carried
forward, and v0.4.1 activation-conflict markers remain manual-review blockers.

## 0.4.1 state-consistency patch

- Current-record selection now applies this exact policy: Active first; if no
  Active exists, newest Pending; otherwise newest ledger record.
- Activation still uses the per-Candidate MySQL advisory lock, but now checks
  for a different Active record inside that lock.
- A conflicting membership remains Pending and the existing Active record is
  unchanged. The completed order receives
  `_click2eu_membership_activation_conflict`,
  `_click2eu_membership_activation_conflict_at`, and an administrator note.
- Reprocessing an already marked conflict does not add another note or falsely
  mark the order activated.
- Candidate status/dashboard output identifies the completed Pending record as
  requiring administrator review rather than awaiting payment.
- No database schema or status was added. Renewal and upgrade decisions remain
  outside this release.

### Conflict review procedure

1. Verify that the order is paid and inspect the Candidate's existing Active
   membership and the conflicting Pending ledger record.
2. Do not delete either ledger record or manually copy activation metadata.
3. Resolve the commercial decision through a future approved upgrade/renewal
   workflow. Version 0.4.1 intentionally performs no automatic replacement.
4. If an administrator later uses a supported manual resolution, document the
   decision and retain the order note for audit history.

## 0.4.0 dashboard update

- Registers the `my-membership` WooCommerce My Account endpoint and adds the
  Candidate-only **My Membership** menu item.
- Provides `[click2eu_membership_dashboard]` using the same renderer.
- Reads current and historical records from the existing ledger and reads
  related orders only through WooCommerce CRUD objects.
- Verifies both ledger and order ownership before rendering an order link.
- Paginates history at ten records per page and safely represents deleted or
  mismatched historical orders as unavailable.
- Performs a one-time rewrite flush on activation or endpoint-version upgrade;
  rewrite rules are not flushed on normal requests.
- Adds no database schema, expiration, renewal or frontend mutation controls.

## 0.3.1 recovery patch

- Enforces exactly one recognized membership item in cart and order paths.
- Revalidates active and pending membership state during checkout.
- Uses a per-Candidate MySQL advisory lock during activation to serialize
  concurrent completed-order handling.
- Records a pending order reversed before activation as `cancelled`.
- Keeps active-order reversals conservative and auditable as `suspended`.

## Architecture

- `click2eu-membership.php`: existing presentation shortcodes, Join form,
  checkout metadata and module bootstrap.
- `Click2EU_Config`: product map and configured page IDs.
- `Click2EU_Auth`: Candidate/Employer checks and account URLs.
- `Click2EU_Memberships`: HPOS-independent membership ledger repository.
- `Click2EU_Orders`: cart validation, pending records, activation and reversal.
- `Click2EU_Access`: frontend, action and REST access policy.
- `Click2EU_Cariera`: Candidate login routing and registration-role hardening.
- `Click2EU_Status`: `[click2eu_membership_status]` output.
- `Click2EU_Dashboard`: My Account endpoint, full dashboard shortcode, current
  record and paginated ledger-history presentation.
- `Click2EU_Time`: filterable UTC clock used by activation, access and tests.
- `Click2EU_Expiration`: unique scheduling, reconciliation and locked expiry.

## Database schema

The plugin creates `${wpdb->prefix}click2eu_memberships` using `dbDelta`.
Each recognized WooCommerce order item has one ledger record, enforced by a
unique `order_item_id` key. Records retain user, order, product, package,
category, status and timestamps. Schema version is stored in
`click2eu_membership_db_version`.

Statuses are `pending`, `active`, `expired`, `suspended` and `cancelled`.
Only a legitimately due dated Active row transitions automatically to Expired;
refund/cancellation audit states are never rewritten as Expired.

The status component is safest on WooCommerce My Account or an accessible
account subpage. Candidate Dashboard is protected for unpaid/pending Candidates,
so it should not be the only location where status is shown.

## Order lifecycle

1. Candidate selects a configured product and WP Job Manager category.
2. Checkout creates an idempotent pending ledger record.
3. BACS/cheque normally places the order On hold; membership remains pending.
4. An administrator verifies payment externally and changes the order to
   Completed.
5. `woocommerce_order_status_changed` activates exactly one record and writes
   `_click2eu_membership_activated` to the order.
6. Failed, Cancelled or Refunded suspends active access and writes an auditable
   suspension marker. History is not deleted and later status changes do not
   silently reactivate the record.

## Protected Candidate functions

Unpaid and pending Candidates are redirected away from the configured Candidate
Dashboard and Submit Resume pages. Resume submission, apply-with-resume POSTs,
and write requests to resume/application REST routes receive a 403 response.
Dashboard, My Profile, My Account, Orders, checkout and public job browsing are
not globally blocked. Employers and administrators are outside Candidate policy.

## Required production configuration

1. Back up production files and database independently.
2. Confirm Cariera Core and Cariera Addons are legitimately licensed and active.
3. Install/replace the plugin using the canonical `click2eu-membership` folder.
4. Create or select a published Join Us page containing
   `[click2eu_packages_section]`.
5. Set `click2eu_join_page_id` to that production page ID. Do not copy the local
   numeric ID.
6. Confirm Candidate Dashboard and Submit Resume page options are configured.
7. Keep Cariera Packages inactive for this independent membership flow.
8. Confirm BACS/cheque instructions and the Completed-order approval procedure.
9. Run the full staging checklist before production activation.
10. Visit **Settings > Permalinks** and save once only if the endpoint returns
    404 after deployment; activation and version upgrade normally flush the
    endpoint automatically.
11. Confirm `/my-account/my-membership/` appears for a Candidate account and
    that `[click2eu_membership_dashboard]` is used only on an authenticated page.

Do not copy the local database to production. It may overwrite live users,
orders, configuration and content.

## Local-only configuration changes

- Password Protected was deactivated to allow guest tests.
- Join Us was created locally as page 11958.
- The stale randomized plugin activation basename was replaced by the canonical
  `click2eu-membership/click2eu-membership.php` basename.
- Cariera Core/Addons activation could not persist because the migrated local
  `cariera_license_activated` flag is false. No license bypass was applied.

## Rollback

1. Deactivate 0.3.0 during a maintenance window.
2. Restore the prior plugin directory from the file backup.
3. Restore a database backup only when safe relative to newer live orders.
4. The ledger table may remain unused during code rollback; do not erase its
   audit history while diagnosing an issue.
5. Rolling code back does not remove schema-v2 columns. Older code ignores them;
   retain them to avoid destroying duration and expiry history.
6. Before rollback, record any queued `click2eu_expire_membership` actions. If
   v0.5.0 is later restored, run reconciliation so overdue rows are processed.
7. v0.6.0 also schedules `click2eu_start_membership`. Before rollback, record
   Scheduled rows and queued starts; v0.5.0 does not understand that state.
8. Never delete renewal columns or Scheduled rows during rollback. Restore
   v0.6.0 and reconcile them after the incident is resolved.
## 0.6.1 mid-term upgrades

No database columns or indexes are added. Existing `renewal_of_membership_id`
links the upgrade row to its source and `renewal_type=upgrade` distinguishes it
from renewal. The source ledger status becomes the auditable terminal state
`upgraded`; its original activation and expiry timestamps remain unchanged.

Financial policy:

- Eligible source basis is the actually paid, post-coupon membership line total,
  less item-attributed refunds, capped by the order total.
- Credit is `basis × remaining_seconds / (duration_days × 86400)`, capped at the
  unrefunded basis and rounded with WooCommerce currency precision.
- Charge is the current trusted target product price less credit, never below zero.
- Taxes must be disabled, currencies must match, order-level/unattributed refunds
  are rejected, and coupons are rejected on the upgrade order.
- Example at exactly half of Basic's 30-day €8 term: €4 credit; Pro at €15 costs
  €11. With one second remaining, the rounded credit is €0.00 and Pro costs €15.

The Candidate submits a nonce-protected server quote. Checkout reuses the normal
WooCommerce order and manual gateway flow. Order and line metadata freeze source
membership/order, target package/category, quote time, paid basis, credit, target
price, final charge, currency and calculation version. Completed approval rechecks
ownership, currency, source effectiveness/payment/refunds, target/category, frozen
total, conflicting rows and one-time credit consumption under the Candidate advisory
lock. The old/new ledger update uses one database transaction.

Do not enable automatic calculation after enabling taxes. Version 0.6.1 fails
closed until an explicit tax-credit policy is implemented and tested. Refunds or
other complex reversals are flagged for manual financial review; the plugin issues
no cash refund and never automatically restores the upgraded source membership.
## 0.6.2 financial-integrity recovery

No schema migration is required. Before deployment, retain the v0.6.1 database
and plugin backup. Version 0.6.2 adds these runtime safeguards:

- `woocommerce_order_refunded` and `woocommerce_order_partially_refunded` both
  trigger payment-lineage validation. Equivalent callbacks share a refund-total
  fingerprint and are idempotent.
- Lineage follows `renewal_of_membership_id` only where `renewal_type=upgrade`,
  remains scoped to one Candidate, detects cycles, and stops after 100 nodes.
- A reversal suspends Active affected rows, cancels Pending/Scheduled affected
  rows, preserves terminal history, adds order notes, and requests review. It
  never restores an earlier membership or creates a cash refund.
- Cross-user malformed children are noted but not mutated. Missing records and
  excessive lineage are fail-closed for discovered same-user access and require
  administrator review.
- Post-transaction finalization writes the source consumption marker, schedules
  expiry, then writes target activation metadata. Each step can be retried. The
  activation marker is written only after scheduling succeeds.
- `init` reconciles at most 50 incomplete Active upgrade finalizations per request.
  Source payment basis and zero-total approval are revalidated during recovery.

For a zero-total upgrade, open its WooCommerce order and run **Order actions →
Approve zero-total Click2EU upgrade**. The user must have `manage_woocommerce`.
The action is recorded with UTC time and administrator ID. Completed status by
itself is insufficient. Positive BACS/manual-payment orders continue to activate
only through the existing Completed workflow.
## 0.6.3 recovery scheduling and quote validity

No database schema migration is required. Recovery state is stored in the
`click2eu_upgrade_recovery_cursor` option. Action Scheduler is preferred; a
five-minute WP-Cron recurrence is the fallback. Each invocation processes at most
25 Active upgrade rows ordered after the cursor and persists progress after every
row. At the end of a sweep the cursor resets to zero for eventual rechecking.
Plugin deactivation removes both recovery schedules.

Checkout quote policy:

- Default validity: 15 minutes, configurable with the
  `click2eu_upgrade_quote_ttl` filter.
- `quoted_at` and `quote_expires_at` are frozen in cart, line and order metadata.
- Checkout rejects expired quotes, expired/changed source memberships, changed
  source payment/refund basis, changed target price, currency or category.
- Classic Checkout uses checkout-validation errors. Shared WooCommerce cart
  validation covers Checkout Blocks; order creation also records a stale/review
  marker as a defensive fallback.
- A placed order is never repriced. Approval compares its frozen credit to the
  currently justified credit. A difference greater than the default €0.01
  tolerance fails closed for administrator review.
- The tolerance is configurable with
  `click2eu_upgrade_approval_credit_tolerance`; changing it is a financial-policy
  decision and must be separately approved and tested.

This policy intentionally does not define a commercial grace period for slow bank
transfers. Until that business decision is approved, materially decayed credit is
blocked rather than automatically repriced, charged or refunded.

## 0.7.0 category entitlement deployment

No schema migration is required. Entitlement is resolved from the existing ledger
using `candidate_user_id`, `status`, `expires_at` and `category_term_id` after
expiration reconciliation. Only the current effective Active row grants access;
Pending, Scheduled, Expired, Suspended, Cancelled and Upgraded history do not.
Legacy Active rows retain the existing no-invented-expiry policy.

Job policy:

- Taxonomy: `job_listing_category` (verified in WP Job Manager and local runtime).
- Match: exact term ID; one match is enough when a job has multiple categories.
- Parent/child relationships do not imply authorization.
- Missing/deleted membership category or category-less job denies only the
  protected action. Public browsing remains available.

Resume policy:

- Cariera Addons source registers `resume` and the distinct `resume_category`.
- Configure `click2eu_resume_category_map` as job-term ID keys whose values are
  arrays of resume-term IDs, or supply the equivalent filter.
- Every selected resume category must be in the configured mapping.
- Create/update/use requires Candidate ownership and an effective membership.
- Unknown mappings fail closed; no name matching or numeric-ID equivalence occurs.

Integration matrix:

| Action | Verified handler/hook | Server resource | Enforcement | Local status |
|---|---|---|---|---|
| Public job browse | WP Job Manager query/template | `job_listing` | No category restriction | PASS |
| Application form | `application_form_validate_fields` | Posted job ID → server post/terms | Exact job category | MOCKED; Cariera runtime BLOCKED |
| Apply with resume | `cariera_addons_resumes_apply_with_resume` POST | Server job/resume posts | Job match, resume owner/mapping | MOCKED; Cariera runtime BLOCKED |
| Resume create/edit | `submit_resume_form_validate_fields` | Server membership + submitted terms | Explicit mapping | MOCKED; Cariera runtime BLOCKED |
| Resume REST write | `/wp/v2/resumes[/ID]` | Route post and server terms | Ownership + mapping | MOCKED; Cariera runtime BLOCKED |
| Resume list AJAX | `resume_manager_get_resumes` | Read-only listing query | Unchanged | Source verified; runtime BLOCKED |
| Admin diagnostics | Click2EU Memberships | Ledger/category terms | Read-only | PASS |

The local active-plugin list does not include Cariera Addons; consequently
`resume`, `job_application` and `resume_category` are not registered in the normal
runtime. Activate only with a legitimate license/configuration, configure mapping,
then execute the browser/server E2E matrix before production deployment.

## 0.7.1 notification deployment

### Authoritative event map

| Event | Authoritative capture point | Validation before delivery |
|---|---|---|
| Payment pending | Successful `Click2EU_Memberships::create_pending()` insert | Ledger row remains Pending and belongs to the Candidate |
| Membership activated | Successful Pending → Active ledger update | Effective Active membership plus Completed, owned order |
| Renewal scheduled | Successful Pending → Scheduled renewal update | Scheduled ledger row plus Completed, owned order |
| Renewal started | Successful Scheduled → Active handoff | Active renewal row plus Completed, owned order |
| Upgrade completed | Upgrade finalization markers persisted | Target Active, source Upgraded, finalization metadata present |
| Expiring 7/3/1 days | Reminder creation after a new activation | Active, unexpired row; unchanged expiry; no Scheduled renewal |
| Expired | Successful Active → Expired update | Row remains Expired and no Active successor renewal exists |
| Suspended / Cancelled | Successful reversal transition | Ledger row remains in the intended terminal state |
| Activation conflict | Persisted order conflict marker | Conflict marker still exists |
| Upgrade review | Persisted upgrade review marker | Review marker still exists |
| Invalid payment lineage | Persisted lineage fingerprint/review marker | Related order and review marker still exist |
| Delivery failure | Third rejected transport attempt | Original notification remains Failed |

No event is generated from a page view. WooCommerce status changes are only an
entry point; success messages are captured after the corresponding ledger or
upgrade transition succeeds.

### Storage and queue

Schema version `1.1.0` creates or upgrades `{prefix}click2eu_notifications`. It stores event
identity, source IDs, queue state, UTC scheduling/attempt timestamps and a short
safe error summary. It does not store recipients, full message bodies, payment
credentials or license data. The unique `event_key` index is the lifecycle
deduplication boundary. Examples include `membership:123:activated`,
`membership:123:expiry-warning:7d` and `order:456:activation-conflict`.

Action Scheduler receives single delivery actions and a five-minute bounded queue
runner when available. WP-Cron provides equivalent fallback events. A worker uses
an atomic status update and five-minute lease before transport. The runner handles
at most 25 due or abandoned rows per pass. Accepted/Cancelled rows default to 180
days retention and Failed rows to 365 days; both periods are filterable.

Transport attempts are limited to three. Rejected attempts retry after 5 minutes,
30 minutes and 2 hours. A terminal failure queues one administrator alert. An
administrator may retry a Failed row from Click2EU Memberships > Notifications;
the action requires `manage_options`, a row-scoped nonce, retains the same event
key and records administrator ID/time. Accepted messages are never automatically
resent. A process crash after the transport accepts a message but before the row
is updated can still produce an unavoidable duplicate; exactly-once external
email delivery cannot be guaranteed.

### Expiry and renewal policy

Warnings are created only for newly activated dated memberships. Legacy Active
rows without expiry receive none. A Scheduled renewal cancels generic warnings on
the current term. If that renewal is later cancelled while the parent remains
Active, eligible future warnings are revived under their original event keys.
Upgrade, suspension, cancellation and expiration cancel stale queued warnings.
The warnings never change membership state.

The upgrade migration deliberately does not enqueue historical lifecycle mail.
Already-Active memberships may be scheduled only through an explicit controlled
call to `Click2EU_Notifications::schedule_reminders( $membership_id )` after an
administrator reviews the intended audience. Do not run an unrestricted backfill.

### Templates, SMTP and privacy

Templates use navy `#12306b`, blue `#2f6bff` and background `#f5f8fd`, with safe
membership/order links and no external fonts. Candidate recipients are resolved
from the ledger's verified WordPress user ID; administrator recipients use the
validated site `admin_email`. Posted recipients and user-provided HTML are never
accepted. `wp_mail()` is the only transport, so production SMTP must be configured
independently and tested on staging. Transport acceptance does not prove delivery.

For test interception, use WordPress's `pre_wp_mail` filter and example.invalid
recipients. Never point fixture users at real addresses. Before staging release:

1. Verify the notification table and recurring Action Scheduler actions.
2. Configure and test the site's sender domain, SPF/DKIM/DMARC and SMTP plugin.
3. Verify Candidate and administrator addresses and support URL.
4. Exercise Pending, activation, renewal, upgrade, refund and expiry transitions.
5. Confirm retry diagnostics without sending to production recipients.
6. Retain the Cariera/browser E2E blocker until legitimate activation is available.

Rollback may deactivate v0.7.2 without dropping the notification ledger. Scheduled
notification actions are unscheduled on deactivation; historical rows remain for
diagnosis and a later safe resume.

## 0.7.2 notification reliability hardening

`event_due_at` is the immutable lifecycle event time. `scheduled_at` is only the
next queue execution time and may move during retry backoff. Expiry validation uses
the deterministic event key and `event_due_at`, never the mutable retry timestamp.

The `1.1.0` migration backfills non-reminder rows from their existing scheduled
time. For v0.7.1 expiry rows it derives the original due time from the membership's
stored `expires_at` and the 7/3/1-day event type. Status, attempt count, last
attempt, accepted time and errors are preserved. Missing memberships fall back to
the existing scheduled timestamp and then fail closed during delivery validation.

Expiry retries are delivered only when all of the following remain true:

- The event key exactly identifies the membership and warning threshold.
- The immutable due time still matches the stored membership expiry.
- The event is due under the current UTC clock and the membership is not expired.
- The membership remains effective Active, with no Scheduled renewal.
- It has not been upgraded, suspended or cancelled.

An expired processing lease below three attempts remains eligible for the next
atomic attempt. An expired lease at attempt three is different: transport outcome
may be unknown, so the runner atomically changes it to `uncertain`, preserves all
attempt data, suppresses automatic resend and creates the deterministic
`notification:{id}:delivery-uncertain` administrator alert. Competing runners use
the same guarded update and cannot overwrite the terminal review state.

The Notifications admin screen exposes an Uncertain filter, original Event Due,
Next Attempt, last attempt and the safe uncertainty explanation. Uncertain rows do
not offer ordinary Failed-row retry and are intentionally excluded from automated
retention cleanup. Operational resolution must consider provider logs before any
manual communication, because the original message may already have been accepted.
The uncertainty explanation is stored in `review_note`; the preceding
`last_error`, attempt count and last-attempt timestamp remain unchanged.

## 0.7.3 dashboard form preservation

Version 0.7.3 is a frontend rendering fix only. `Click2EU_Dashboard::account_content()`
continues to sanitize rendered dashboard HTML, but explicitly permits the limited
`form`, `input` and `button` attributes required by the existing nonce-protected
renewal and upgrade actions. No schema migration is required.

Before deployment, confirm the My Membership endpoint contains working renewal
and eligible-upgrade forms, then complete one manual-payment upgrade in staging.
Rollback to 0.7.2 does not alter stored data, but it again removes these controls
from the rendered My Account endpoint. Cariera-dependent browser validation remains
blocked until the vendor integration can be legitimately activated.
