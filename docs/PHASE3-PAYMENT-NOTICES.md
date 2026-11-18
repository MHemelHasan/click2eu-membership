# Phase 3.1: Contextual Candidate Membership Notices

## Placement

No stable Cariera hook for inserting content near the top of the My Profile
main content is present in this repository. In Elementor, edit the page mapped
at **Click2EU Memberships > Settings > Page Mapping > My Profile**, add a
**Shortcode** widget near the top of the main profile-content column, and enter:

`[click2eu_payment_status_notice]`

The owner has placed the same shortcode once on the mapped Candidate Dashboard.
Do not place it in a global header, footer, or template shared with Employers.
Exclude My Profile, Candidate Dashboard and WooCommerce My Account pages from
full-page caching. The shortcode also sets `DONOTCACHEPAGE` and no-cache headers
when possible, but the cache/CDN configuration remains the authoritative layer.

## Server-side state rules

- Logged-out visitors and non-Candidates receive no output.
- An effective Active ledger membership with an owned, matching Completed order
  and no activation-conflict marker may display the Active notice for 24 hours
  after its UTC `activated_at` timestamp.
- Active success dismissal uses a POST nonce and user meta keyed by a SHA-256
  identity of membership ID plus `activated_at`; a later activation gets a new
  identity. Dismissal never changes order or membership state.
- Active access takes precedence over historical Pending initial rows.
- An owned Pending renewal/upgrade order remains visible after Active success is
  dismissed or expires; a Scheduled renewal does not show a payment warning.
- With no effective Active membership, the bounded Pending-initial ledger query
  may display an owned matching On hold or Pending payment order.
- The latest owned ledger row that is genuinely Expired may display the Expired
  notice when no effective Active membership or unresolved initial order wins.
- Processing initial, failed, cancelled, refunded, suspended, mismatched and
  unowned records produce no invented payment notice.

## My Membership and Join Us

My Membership calls the same notice renderer above Current Membership. A
per-request guard prevents duplicate output if the shortcode is also present.
The existing sections, renewal forms and upgrade forms remain unchanged.

Only the page mapped as **Join Us** receives account-context presentation.
Owned On hold/Pending initial orders show their validated plan, category, order
number, WooCommerce status and ledger status above the existing four cards. The
cards remain visible, but initial Join forms become My Membership links. Active
Candidates get management links unless they are following the existing valid
renewal continuation. Homepage uses of the same pricing shortcodes are outside
this page check and retain their established presentation.

## Staging acceptance checklist

For every scenario, inspect the WordPress user ID, WooCommerce order customer,
order status, order item/product and the Click2EU ledger user/order/item/status.

1. Place a new BACS membership order. Confirm one Candidate user, one On hold
   owned order, one Pending ledger row and the blue verification notice.
2. Change On hold to Pending payment. Refresh My Profile and confirm the amber
   notice updates without creating a user, order or ledger row.
3. Complete the order. Confirm the ledger is Active/effective, no activation
   conflict exists and the green Active notice appears for no more than 24
   hours from `activated_at`.
4. Dismiss Active success on My Profile. Confirm the POST nonce is accepted and
   the same activation stays hidden on Candidate Dashboard and My Membership.
   Confirm a failed/cross-user nonce does not write dismissal meta.
5. Simulate a later activation with a new membership/`activated_at` identity and
   confirm a fresh success notice may appear.
6. Add an activation-conflict marker to a test Completed order through the real
   conflict workflow. Confirm no false Active notice appears.
7. With an On hold Basic initial order, visit Join Us and confirm the validated
   plan/category/order/status summary. Click normal Pro, Premium and Elite Join
   buttons. Each must redirect to My Membership; order and ledger counts must
   remain unchanged.
8. With an Active membership, click a normal pricing-card Join. Confirm redirect
   to My Membership and no new initial order. Confirm a server-validated renewal
   and the dedicated upgrade flow still reach checkout.
9. While Active, create a Pending renewal and then a Pending upgrade in separate
   test runs. Confirm Active remains primary and the secondary awaiting-payment
   message appears. Confirm a Scheduled renewal does not show that warning.
10. Dismiss or age out Active success while a Pending continuation exists and
    confirm the persistent renewal/upgrade payment message remains.
11. Expire an owned Completed membership through the real expiration mechanism.
    Confirm the persistent Expired notice on all three surfaces, with no claim
    that a resume was unpublished. Confirm old Expired rows never override Active.
12. Confirm failed, cancelled, refunded and suspended records show no invented
    payment state.
13. View the shortcode logged out and as an Employer. Confirm it renders no
    Candidate data or markup.
14. Check desktop, tablet and 320px mobile layouts, keyboard-focus visibility,
    link destination, screen-reader heading/link names and color contrast.
15. With page cache/CDN enabled, confirm these private pages and Join Us are
    excluded for authenticated Candidates and a
    second Candidate can never receive the first Candidate's rendered notice.
16. Confirm the homepage cards, Resume Manager/Add Resume controls, category
    warning, Classic Checkout, registration, valid renewal and upgrade flows are
    unchanged.

These are manual staging checks. Local PHP lint and contract checks do not prove
WordPress, WooCommerce, Cariera, Elementor, CDN or browser integration.
