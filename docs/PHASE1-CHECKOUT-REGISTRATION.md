# Phase 1: Classic Checkout Candidate Registration

## Scope

This phase changes only the initial account-entry path:

- a logged-out pricing-card submission goes directly to Classic Checkout;
- the validated Click2EU membership cart requires account creation;
- the created WordPress user receives the existing Cariera `candidate` role;
- existing WordPress emails must authenticate through WooCommerce's checkout
  login form; and
- logged-in non-Candidate accounts are rejected without role changes.

Order lifecycle, membership activation, duplicate-order policy, payment status
notices and custom bank-transfer copy are not changed in this phase.

## WooCommerce and WordPress prerequisites

1. The Checkout page must use WooCommerce Classic Checkout, not the Checkout
   Block.
2. WooCommerce Direct Bank Transfer (BACS) must remain enabled and configured
   in WooCommerce > Settings > Payments. Do not store bank credentials here.
3. Outbound WordPress email must be configured so WooCommerce can deliver its
   standard new-account/password-setup email.
4. Cariera/WP Job Manager must register the `candidate` role. Checkout fails
   closed with a support message if that role is unavailable.
5. The existing Click2EU product IDs, job-category taxonomy and Join shortcodes
   must remain configured as before.

The plugin scopes checkout registration, mandatory accounts, the checkout login
prompt and generated-password behavior to a server-validated, one-item Click2EU
membership cart. Global WooCommerce account settings and unrelated checkouts are
not changed.

## Manual staging acceptance

Use a staging site with the production theme/plugin versions and an empty test
cart. Record the WooCommerce version under test.

1. Logged out, select Basic and a valid job category, then press Join. Confirm
   the browser opens the clean Classic Checkout URL and the order review keeps
   the selected product, category and quantity 1.
2. Confirm Checkout offers account creation and the WooCommerce login prompt.
   Place a BACS order with a new email.
3. In WordPress Users, confirm exactly one user exists for that email and its
   role is Candidate. Confirm the WooCommerce order belongs to that user. Do not
   mark the order Completed during this Phase 1 check.
4. Confirm the standard WooCommerce new-account email provides the secure
   password-setup mechanism and does not disclose a plain-text password.
5. Log out, repeat Join with the same email, and attempt checkout. Confirm no
   duplicate user or guest order is created, the error asks for authentication,
   and the checkout login form is available.
6. Sign in through that form. Confirm the membership cart and category remain
   present and Checkout resumes.
7. Sign in as an Employer or another non-Candidate account and repeat Join.
   Confirm checkout is blocked and the account's roles remain unchanged.
8. Perform a normal unrelated WooCommerce checkout/account registration and
   confirm it retains WooCommerce's configured registration and role behavior.
9. Confirm the existing Candidate dashboard/access controls still block resume
   creation for an unpaid Candidate. Payment-state notices are Phase 3 scope.

Browser, email-delivery, Cariera initialization and order-ownership acceptance
require this staging run; repository PHP lint alone does not establish them.
