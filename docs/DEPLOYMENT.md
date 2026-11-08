# Click2EU Membership 0.4.0

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

## Database schema

The plugin creates `${wpdb->prefix}click2eu_memberships` using `dbDelta`.
Each recognized WooCommerce order item has one ledger record, enforced by a
unique `order_item_id` key. Records retain user, order, product, package,
category, status and timestamps. Schema version is stored in
`click2eu_membership_db_version`.

Statuses used by 0.3.1 are `pending`, `active`, `suspended` and `cancelled`.
`expired` is reserved for later policy. No expiry calculation is implemented.

Version 0.4.0 does not change this schema or status lifecycle.

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
