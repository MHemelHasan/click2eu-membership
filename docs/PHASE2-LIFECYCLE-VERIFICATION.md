# Phase 2: Order and Membership Lifecycle Verification

## Implemented boundaries

- WooCommerce order status and Click2EU ledger status remain separate.
- `on-hold`, `pending`, and `processing` do not activate membership.
- Only an administrator transition to `completed` invokes existing activation,
  renewal scheduling, or upgrade activation logic.
- Initial duplicate detection requires a Pending initial ledger row, matching
  Candidate ownership, matching order item/product, and a WooCommerce order in
  `pending`, `on-hold`, or `processing`.
- Renewal and upgrade rows are excluded from the initial-order lookup.
- Pending Click2EU BACS membership orders are excluded from WooCommerce's global
  unpaid-order timeout only after ledger, user, order item, and product checks.

## Hold Stock configuration

WooCommerce > Settings > Products > Inventory > Hold stock (minutes) applies to
checkout orders in Pending payment when global stock management is enabled. It
does not cancel On hold orders. Click2EU v0.8.0 preserves validated Pending BACS
membership orders in code, so the store-wide value can remain appropriate for
unrelated products. Do not globally disable inventory or cancellation solely for
Click2EU.

## Manual staging matrix

For every order, record the WordPress user ID, WooCommerce order ID and order
item ID. In the Click2EU ledger, confirm `candidate_user_id`, `order_id`,
`order_item_id`, product, package, category, duration and renewal metadata match.

1. New BACS checkout: one Candidate, one On hold order and one Pending ledger
   row. Activation and expiration timestamps remain empty.
2. Attempt Join again while On hold: redirect to My Membership; no second order
   or ledger row. The owned order remains available from My Membership.
3. Change On hold to Pending payment: ledger remains Pending and protected
   features remain locked.
4. Run the WooCommerce unpaid-order scheduled action after the configured Hold
   Stock age: the validated Click2EU BACS order remains Pending payment. Confirm
   an unrelated eligible Pending order is still cancelled normally.
5. Attempt Join while Pending payment: the existing owned order is shown and no
   new initial order is created.
6. Change On hold to Completed: ledger becomes Active once; `activated_at` and
   `expires_at` are populated once and the order activation marker is present.
7. Repeat Completed processing or save the order again: membership count and
   timestamps do not change.
8. Change Pending payment to Completed: the same Pending row becomes Active;
   there is no replacement row.
9. Change On hold or Pending payment to Cancelled/Failed: its Pending row becomes
   Cancelled and no access is granted.
10. Refund a Completed initial order: its membership becomes Suspended and its
    expiration action is removed.
11. Active Candidate presses a normal pricing-card Join: no initial order is
    created. Start renewal from My Membership and confirm the existing same-plan,
    same-category scheduled-renewal behavior still works.
12. Complete a valid upgrade using its existing flow and confirm source/target
    lineage, frozen quote checks and activation markers remain intact.
13. Create an unrelated WooCommerce order and verify its guest/account rules,
    unpaid timeout, statuses and metadata are unchanged.

These are staging acceptance steps. PHP lint and isolated contract checks do not
prove WordPress, WooCommerce cron, BACS, email, Cariera or browser integration.
