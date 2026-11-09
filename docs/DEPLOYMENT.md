# Click2EU Membership 0.5.0

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
