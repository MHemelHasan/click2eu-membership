# Contributing to Click2EU Membership

## Branch strategy

- `main` contains reviewed, release-ready snapshots.
- `develop` is the integration branch for tested development work.
- Create `feature/<short-description>` branches from `develop`.
- Merge feature branches into `develop` only after relevant tests pass.
- Promote `develop` to `main` through a reviewed release change. Do not merge
  untested work directly into `main`.

## Commit messages

Use concise imperative messages following Conventional Commits where practical:

- `feat:` new user-facing functionality
- `fix:` confirmed defect correction
- `test:` test-only changes
- `docs:` documentation only
- `chore:` repository, build or maintenance work
- `refactor:` internal restructuring without behavior changes

Keep commits narrowly scoped. Do not commit database exports, production data,
credentials, license keys, generated release archives or local configuration.

## Local development workflow

1. Work in a LocalWP clone, never directly against production.
2. Create database and plugin-file recovery checkpoints before schema or
   integration changes.
3. Branch from `develop` using `feature/*`.
4. Keep third-party WordPress, WooCommerce, WP Job Manager and Cariera source
   read-only.
5. Implement Click2EU behavior in this plugin and preserve its public shortcodes.
6. Inspect `git diff` and run a sensitive-data scan before committing.

## Testing requirements

At minimum, a change must receive PHP syntax checks and targeted integration
tests. Changes affecting orders or access control must test:

- Guest, Candidate, Employer and Administrator separation.
- Pending, Completed, Failed/Cancelled/Refunded and repeated hook handling.
- Ledger uniqueness and order ownership.
- Classic Checkout metadata and HPOS-compatible WooCommerce CRUD.
- Protected frontend, POST, AJAX and REST operations as applicable.
- Existing pricing shortcodes and the customized dropdown CSS.

Browser tests are required for release candidates when Cariera is legitimately
active. The v0.3.1 baseline passed independent membership integration tests but
has not passed complete Cariera registration/login or browser checkout E2E tests.

Never send real transactional messages or external webhooks during automated
tests. Use isolated `example.invalid` users and remove test data safely.

## Versioning and releases

Use semantic versions:

- Patch: confirmed bug fixes and hardening without new workflow behavior.
- Minor: backward-compatible features.
- Major: incompatible schema, API or business-flow changes.

Update the plugin header, internal version, README and release documentation
together. Tag reviewed release snapshots. Build deployment ZIPs from an explicit
allowlist and exclude `.git`, dumps, logs, secrets and test artifacts.

## Deployment and rollback

- Deploy code only; never copy the LocalWP database over production.
- Back up production files and database independently before deployment.
- Verify required pages, products, roles, gateways and Cariera license state.
- Stage and test before production activation.
- For rollback, restore the prior plugin code first. Restore a database backup
  only after assessing orders/users created since that backup.
- Preserve membership ledger history during diagnosis; do not drop it casually.
