# Phase 3: Candidate Frontend Payment Status Notices

## Placement

No stable Cariera hook for inserting content near the top of the My Profile
main content is present in this repository. In Elementor, edit the page mapped
at **Click2EU Memberships > Settings > Page Mapping > My Profile**, add a
**Shortcode** widget near the top of the main profile-content column, and enter:

`[click2eu_payment_status_notice]`

Optionally place the same shortcode once on the mapped Candidate Dashboard.
Do not place it in a global header, footer, or template shared with Employers.
Exclude My Profile, Candidate Dashboard and WooCommerce My Account pages from
full-page caching. The shortcode also sets `DONOTCACHEPAGE` and no-cache headers
when possible, but the cache/CDN configuration remains the authoritative layer.

## Server-side state rules

- Logged-out visitors and non-Candidates receive no output.
- An effective Active ledger membership with an owned, matching Completed order
  and no activation-conflict marker displays the Active notice.
- Active access takes precedence over historical Pending initial rows.
- An owned Pending renewal/upgrade order adds a secondary message; a Scheduled
  renewal does not show a payment warning.
- With no effective Active membership, the bounded Pending-initial ledger query
  may display an owned matching On hold or Pending payment order.
- Processing, failed, cancelled, refunded, expired, suspended, mismatched and
  unowned records produce no invented payment notice.

## Staging acceptance checklist

For every scenario, inspect the WordPress user ID, WooCommerce order customer,
order status, order item/product and the Click2EU ledger user/order/item/status.

1. Place a new BACS membership order. Confirm one Candidate user, one On hold
   owned order, one Pending ledger row and the blue verification notice.
2. Change On hold to Pending payment. Refresh My Profile and confirm the amber
   notice updates without creating a user, order or ledger row.
3. Complete the order. Confirm the ledger is Active/effective, no activation
   conflict exists and the green Active notice replaces the payment notice.
4. Add an activation-conflict marker to a test Completed order through the real
   conflict workflow. Confirm no false Active notice appears.
5. With an On hold Basic initial order, click normal Pro, Premium and Elite Join
   buttons. Each must redirect to My Membership; order and ledger counts must
   remain unchanged.
6. With an Active membership, click a normal pricing-card Join. Confirm redirect
   to My Membership and no new initial order. Confirm a server-validated renewal
   and the dedicated upgrade flow still reach checkout.
7. While Active, create a Pending renewal and then a Pending upgrade in separate
   test runs. Confirm Active remains primary and the secondary awaiting-payment
   message appears. Confirm a Scheduled renewal does not show that warning.
8. Confirm old Pending orders do not override current Active entitlement.
9. Confirm failed, cancelled and refunded orders show no payment instructions;
   expired and suspended memberships show no Active notice.
10. View the shortcode logged out and as an Employer. Confirm it renders no
    Candidate data or markup.
11. Check desktop, tablet and 320px mobile layouts, keyboard-focus visibility,
    link destination, screen-reader heading/link names and color contrast.
12. With page cache/CDN enabled, confirm these private pages are excluded and a
    second Candidate can never receive the first Candidate's rendered notice.

These are manual staging checks. Local PHP lint and contract checks do not prove
WordPress, WooCommerce, Cariera, Elementor, CDN or browser integration.
