# QA Blockers and Remaining Limits

## BLOCKED — Cariera-dependent browser workflows

Cariera Core/Addons cannot remain active in this local environment without a
legitimate vendor activation. No license bypass or activation attempt was made.
The following remain BLOCKED:

- Cariera Candidate registration and login
- Real Resume Manager create/edit
- Real Apply With Resume
- Employer application management
- Actual Cariera Addons integration

Independent WordPress, WooCommerce, membership, category-policy REST protection
and public-job behavior were tested without claiming these vendor workflows.

## NOT TESTED

- Checkout Block / Store API checkout: the site uses Classic Checkout only.
- Real SMTP acceptance, provider logs, spam placement and inbox delivery: all
  `wp_mail()` calls were intercepted with example.invalid recipients.
- A browser session changing users after checkout initialization.
- A zero-total upgrade browser approval fixture.
- Distinct-process concurrency/load testing.
- Production-scale Action Scheduler throughput.
- A destructive process kill in the narrow post-mail-transport update window.

## Production blockers

Version 0.7.3 is suitable for continued v0.8.0-B development, but production
release remains blocked on legitimate Cariera activation and its full browser E2E,
staging SMTP/provider verification, Checkout Block testing if the production page
uses that block, and normal staging acceptance of the 0.7.3 dashboard form fix.

The LocalWP optional Imagick extension warning should be corrected for a clean
local PHP startup, although it did not affect membership QA. The generic WordPress
Admin heartbeat notice seen in headless Chrome should be rechecked in an interactive
staging browser; order writes themselves completed and persisted successfully.
