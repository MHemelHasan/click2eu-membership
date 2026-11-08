Click2EU Membership (v0.3.1)
=======================================

PHASE 2 UPDATE:
- Adds a persistent Candidate membership ledger.
- Creates pending records for recognized membership orders.
- Activates only when an administrator moves the order to Completed.
- Suspends access after Failed, Cancelled or Refunded reversals.
- Adds Candidate role/cart validation, protected feature checks, Cariera login
  routing and [click2eu_membership_status].
- Durations remain display-only; automatic expiry is not implemented.

v0.3.1 hardens one-package-per-order enforcement, revalidates active/pending
membership state at checkout, serializes per-Candidate activation, and records
pre-activation order reversals as cancelled rather than pending.

See docs/DEPLOYMENT.md and docs/TEST-REPORT.md for the v0.3.x architecture,
configuration, test evidence and rollback procedure.

UPDATE: redesigned from the user-provided Click2EU homepage HTML PLANS section.
The plugin retains the v0.1.0 WooCommerce checkout behavior and original folder/slug.

PRODUCT IDS (already mapped):
  Basic = 11868 / Pro = 11869 / Premium = 11870 / Elite = 11871

INSTALL / UPGRADE:
1. BACK UP your site first; test on staging.
2. WP Admin > Plugins > Add New > Upload Plugin.
3. Upload this ZIP. If WordPress detects a prior Click2EU Membership plugin,
   click 'Replace current with uploaded'. Activate it if required.
4. Clear any page/cache plugin and browser cache so v0.2.0 CSS appears.
5. Edit your Elementor page and place a Shortcode widget.

SHORTCODES (THE THREE PRESENTATION MODES):
  [click2eu_packages_section]
      Full HTML-inspired section: small PLANS label, main heading, subtitle,
      four cards, disclaimer underneath.

  [click2eu_packages]
      Four cards ONLY (no heading/subheading/disclaimer).

  [click2eu_package package="basic"]
  [click2eu_package package="pro"]
  [click2eu_package package="premium"]
  [click2eu_package package="elite"]
      ONE complete card, including category dropdown and Join button.

  [click2eu_join package="basic"]
      Legacy shortcode: dropdown + Join button ONLY for your OWN Elementor card.
      'pro', 'premium', 'elite' also work. This has NOT been removed.

  [click2eu_packages section="yes"]
      Alias for full section if you want to use the existing shortcode name.

OPTIONAL SHORTCODE ATTRIBUTES:
  show_duration="yes"  (default yes; 30/90/180/365 days on front end)
  show_day_rate="yes"  (default yes; approximate price/day from live WC price)
  show_duration="no"   (hide duration and per-day line entirely)

  Full section text can be overridden, e.g.:
  [click2eu_packages_section subtitle="One payment, no auto-renewal. Pay manually at checkout."]
  It also accepts eyebrow="", title="", disclaimer="", id="plans".

DISPLAY COPY / FEATURES:
- Colors, spacing, typography, four columns and Elite badge follow the
  provided Click2EU reference HTML screenshot (no Tailwind dependency).
- Product price is read from WooCommerce (not hard-coded); whole amounts
  visually omit trailing .00, like the provided screenshot.
- Each product Short Description takes PRIORITY for the feature list.
- If a product's Short Description is EMPTY, the copy from the uploaded
  Click2EU homepage HTML is used as its fallback feature list.
- The default subtitle from the HTML says 'Pay by bank transfer'. If you use
  Check Payments or COD instead, change subtitle via the shortcode as above.
- Claims about profile views, prioritization, support, video introductions
  are UI copy only; these features are NOT enabled by this Phase 1 plugin.
  Edit Product Short Descriptions to avoid promising unavailable features.

CATEGORY AND CHECKOUT (UNCHANGED FROM v0.1.0):
- WP Job Manager taxonomy 'job_listing_category' feeds the dropdown.
- Every card shows required dropdown directly above the full-width Join button.
- Join requires sign-in, validates nonce/category/product server-side, then adds
  the selected product (qty 1), removes other cart items and redirects to the
  CLEAN WooCommerce checkout URL (no ?add-to-cart or product ID).
- Checkout displays the chosen Job Category and package feature copy.
- WooCommerce order items and order meta store the category / package.
- Supports WooCommerce Checkout Block and classic checkout item metadata.
- Manual gateways must be enabled via WooCommerce Payments; COD for virtual
  products requires 'Accept for virtual orders'.

IMPORTANT: DISPLAY-ONLY DURATION
  30/90/180/365 days and the approximate per-day cost are text/marketing labels.
  They DO NOT start a membership timer, grant access, restrict user capabilities,
  renew plans or automatically expire membership after any number of days.
  Those membership behaviors belong in a future phase.

TEST CHECKLIST:
[ ] Existing four product IDs still load their live prices.
[ ] Section shortcode displays header, 4 cards and disclaimer.
[ ] Cards-only shortcode renders no heading or disclaimer.
[ ] Each single-card shortcode renders exactly one full card.
[ ] Existing click2eu_join shortcodes still work.
[ ] Dropdown sits directly above Join button and requires a category.
[ ] Category from WP Job Manager shows in checkout & order details.
[ ] Checkout URL has no add-to-cart or category parameters.
[ ] Small screen: cards stack vertically and do not overflow.
[ ] Offline manual payment order completes without payment activation promises.

Safety: PHP lint passed at packaging time. The ZIP was not installed on your live
WordPress/Cariera site; verify the flow on staging before going live.
