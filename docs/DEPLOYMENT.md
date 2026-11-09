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
