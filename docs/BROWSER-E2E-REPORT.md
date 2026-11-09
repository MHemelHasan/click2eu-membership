# v0.8.0-A Independent Browser E2E Report

## v0.7.4 Phase-1 access-control addendum (2026-11-10)

This addendum records current feature-branch evidence; the historical v0.8.0-A
report below remains unchanged.

- Browser control: **BLOCKED**. The Chrome extension exposed the local tabs but
  its tab create/attach adapter failed with an internal `timeoutMs` error after
  reset and retry. No HTTP result is presented as a browser result.
- Local Cariera runtime: **BLOCKED**. Cariera Core 2.0.2 and Addons 1.1.1 are
  installed but inactive, so vendor UI, console, network and persistence E2E
  cannot be honestly completed.
- WordPress integration: **PASS, 35 assertions / 0 failures** using isolated
  example.invalid users, membership rows, jobs, companies, resume and terms.
- Real local HTTP: **PASS** for guest/unpaid redirects, allowed account routes,
  exact-category Active job detail, nonmatching denial, listing AJAX 401/403,
  application/resume/message POST denial, feed/search non-disclosure, sitemap
  suppression and private/no-store response headers.
- A real HTTP defect was found and fixed: singular protected queries were being
  emptied in `pre_get_posts`, causing 404 before the intended Join/category
  redirect. Singular main queries now resolve for `template_redirect`; list and
  archive queries remain server-filtered.

Because real browser and active vendor-handler tests are blocked, v0.7.4 was not
version-bumped, packaged, merged into `develop`, or declared launch-ready.

## Scope and environment

- Site: `http://click2eu.local/` (LocalWP only)
- WordPress 7.1.4, PHP 8.4.27, WooCommerce 11.2.2
- Plugin under test: 0.7.2; focused fix version: 0.7.3
- WooCommerce HPOS: enabled; Checkout: Classic shortcode; manual gateways: BACS and cheque
- Browser: installed Chrome 156 in headless mode, driven through Chrome DevTools Protocol 1.3
- Evidence: `C:\Users\hemel\Local Sites\click2eu\qa-artifacts\v0.8.0-a`
- Recovery: local tag `v0.7.2-pre-browser-qa`, plugin ZIP and database dump under
  `C:\Users\hemel\Local Sites\click2eu\qa-backups\v0.8.0-a-preflight`

The Chrome extension automation provider was unavailable because its navigation
adapter raised an internal `timeoutMs` error. Chrome DevTools Protocol was used as
the equivalent local browser driver. These are real rendered-page, DOM, console,
network and screenshot checks, not HTTP-only tests.

## Browser results

| Workflow | Result | Evidence |
|---|---|---|
| Join Us desktop/mobile, four plans and category retention | PASS | Real browser: `join-us-desktop.png`, `join-us-mobile.png` |
| Required empty category browser validation | PASS | Real browser DOM validity |
| Basic, Pro, Premium and Elite Classic Checkout | PASS | Real browser screenshots and WooCommerce readback |
| Product/category cart and order-item metadata | PASS | Real browser plus WooCommerce HPOS integration |
| Pending before approval; no premature entitlement | PASS | Real browser plus ledger readback |
| Administrator completion and repeated completion | PASS | Real browser plus integration readback |
| Candidate dashboard, history, ownership and order links | PASS | Real browser: `candidate-a-active-membership.png` |
| Candidate cross-user order access | PASS | Real browser returned “Invalid order.” |
| Employer/admin endpoint restrictions | PASS | Real browser returned HTTP 403 |
| Basic to Pro upgrade (€8 credit, €7 total) | PASS | Real browser screenshots and ledger readback |
| Basic to Premium upgrade (€8 credit, €21 total) | PASS | Real browser screenshots and ledger readback |
| Pro to Elite upgrade (€15 credit, €40 total) | PASS | Real browser screenshots and ledger readback |
| Elite early renewal (€55) and Scheduled state | PASS | Real browser screenshots and ledger readback |
| Public matching job remains browsable | PASS | Real browser: `public-matching-job.png` |
| Membership and Notifications admin screens | PASS | Real browser desktop/mobile screenshots |
| Failed notification retry capability and nonce | PASS | Real browser: admin allowed; Candidate denied with HTTP 403 |
| Checkout Block / Store API checkout | NOT TESTED | No Checkout Block is configured on the local checkout page |
| Cariera authentication/application/resume workflows | BLOCKED | Cariera Core/Addons inactive pending legitimate licensing |

All four approved plans stored the expected elapsed durations: Basic 30 days,
Pro 90, Premium 180 and Elite 365. Controlled integration timestamps verified
expiration, repurchase and delayed renewal handoff without changing the system clock.

## Confirmed defect

**Severity: HIGH — renewal and upgrade controls removed from My Account HTML**

1. Activate an eligible membership and open My Account > My Membership.
2. Observe that upgrade quotes/buttons are visually described but the DOM contains
   no actionable forms or hidden nonce inputs.
3. Expected: nonce-protected upgrade and renewal forms are submitted to the existing
   server handlers. Actual: `wp_kses_post()` strips `form` and `input` elements.
4. Source: `includes/class-click2eu-dashboard.php`,
   `Click2EU_Dashboard::account_content()`.
5. Fix: retain `wp_kses()` sanitization and narrowly allow the required form, input
   and button attributes.
6. Regression: all three browser upgrade flows and the Elite renewal flow completed;
   source/target states, credits, full new terms and idempotency were correct.

## Errors and observations

- No unexpected JavaScript exceptions, failed browser requests, HTTP 500 responses
  or new PHP fatal errors occurred in the final browser pass.
- Browser console HTTP 403 entries were the intentional Candidate/employer denial tests.
- WordPress Admin intermittently displayed its generic “Connection lost. Saving
  disabled” heartbeat notice under headless Chrome; every HPOS save returned “Order
  updated,” persisted correctly, and had no corresponding failed request. This is
  recorded as an environment observation, not a plugin defect.
- LocalWP emits a PHP startup warning for its missing optional `php_imagick.dll`.
- Historical log entries from deliberately failing QA harness assertions were not
  treated as browser/runtime failures; final checks were clean.

## Cleanup

The isolated mail interceptor was removed. Eight example.invalid users, 22 test
orders, 22 test ledger rows (including pagination history), 63 notification rows,
five jobs, three terms and all matching scheduled fixture actions were deleted.
The isolated Chrome process/profile and temporary PHP/JavaScript harnesses were
removed. Screenshots and verified preflight backups were retained outside the repo.
