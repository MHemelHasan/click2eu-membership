# Pending BACS Payment Instructions

## Visibility boundary

The card renders only from WooCommerce My Account > My Membership. The full
`[click2eu_membership_dashboard]` shortcode and the standalone
`[click2eu_payment_status_notice]` shortcode do not render it.

Eligibility requires an authenticated Candidate with no effective/current
Active membership and a bounded Pending initial ledger row whose owned order:

- is a WooCommerce `WC_Order` owned by the same user;
- has exact WooCommerce status `pending` and payment method `bacs`;
- has no activation-conflict marker;
- contains the ledger-referenced order item and recognized membership product;
- is not a renewal or upgrade.

Every invariant is checked again before rendering. All order reads use
WooCommerce CRUD and remain HPOS-compatible.

## Configuration sources

Bank accounts come from the WooCommerce BACS gateway account collection and its
order-specific `woocommerce_bacs_accounts` filter. Every non-empty configured
account is displayed. Gateway instructions come from the active BACS gateway,
with `woocommerce_bacs_settings` as a safe fallback.

Configure the optional support number at **Click2EU Memberships > Settings >
Payment Support**. The value must be an international number with country code;
it is stored in digits-only wa.me format. Invalid input preserves the previous
valid value. The setting does not change WooCommerce BACS configuration.

## Staging checklist

1. Pending initial BACS: status notice, instructions card, Current Membership
   and History all render in their expected order.
2. Change the same order to On hold, Processing, Completed, Cancelled, Failed or
   Refunded and confirm the instructions card disappears immediately.
3. Confirm Active plus Pending renewal and Active plus Pending upgrade never
   show the initial-payment card and retain their existing sections/access.
4. Confirm Expired without a new validated initial order shows no card; create a
   valid new Pending initial BACS order and confirm the card then appears.
5. Change payment method away from BACS and confirm the card disappears.
6. Test another Candidate, Employer, administrator and guest; confirm no order
   or bank information leaks.
7. Test empty BACS configuration, one account, and multiple accounts. Confirm no
   empty rows or fabricated values, and every configured account is identified.
8. Confirm total and currency exactly match the owned WooCommerce order, not the
   current product price.
9. Save a valid and invalid WhatsApp number. Confirm mappings/policies remain
   unchanged, valid links include the verified order number, and invalid/missing
   configuration creates no link.
10. Test Account Number and IBAN Copy buttons with keyboard and pointer input,
    Clipboard API available/unavailable, and JavaScript disabled.
11. Verify the endpoint at desktop, tablet and 320px mobile widths with long
    account values and no horizontal scrolling.
12. Confirm both existing shortcodes outside the endpoint remain unchanged and
    no duplicate card appears.
13. Re-run renewal, upgrade, activation, expiration, pagination, checkout and
    HPOS staging regressions without changing real customer orders.

These are manual WordPress/WooCommerce checks. Static source checks do not prove
runtime gateway configuration, browser behavior, caching, or integration.
