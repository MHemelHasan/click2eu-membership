# v0.8.0-A Integration Matrix

| Area / scenario | Status | Evidence source |
|---|---|---|
| Plugin active; schemas 3.0.0 / 1.1.0 | PASS | WP-CLI and database readback |
| HPOS compatibility and order CRUD | PASS | WP-CLI, integration, real browser admin |
| Action Scheduler availability and group runner | PASS | WP-CLI (`0` due tasks, clean completion) |
| Notification WP-Cron runner/cleanup fallback | PASS | Isolated WP-CLI integration (300s / 86400s) |
| Join cards and product mappings 11868–11871 | PASS | Real browser and integration |
| Responsive desktop/mobile card layout | PASS | Real browser screenshots; no body overflow |
| Empty category selection | PASS | Real browser native required-field validation |
| Tampered invalid category POST | PASS | WordPress integration, server-side validation |
| Classic Checkout, all four plans | PASS | Real browser |
| Checkout Blocks / Store API | NOT TESTED | Checkout Block not configured |
| Order ownership and cross-user isolation | PASS | Real browser and integration |
| Duplicate active/pending purchase rejection | PASS | Real browser plus integration |
| Login/user change checkout validation | NOT TESTED | No separate browser session mutation executed |
| Pending to Completed to Active | PASS | Real browser and integration |
| Completion callback idempotency | PASS | Real browser admin repeat plus integration |
| 30/90/180/365-day expiry timestamps | PASS | Integration readback |
| Remaining days and history pagination | PASS | Real browser and integration |
| Exact expiration and expired access denial | PASS | Controlled WordPress integration |
| Expired Candidate repurchase | PASS | WordPress integration |
| Early renewal Scheduled state and original preservation | PASS | Real browser and integration |
| Delayed renewal boundary, no unearned access | PASS | Controlled WordPress integration |
| Basic→Pro, Basic→Premium, Pro→Elite | PASS | Real browser and integration |
| Quote credit/total and full target term | PASS | Real browser and integration |
| Expired upgrade quote rejection | PASS | WordPress integration |
| Credit single consumption / recovery idempotency | PASS | WordPress integration |
| Refund lineage suspension | PASS | WordPress integration |
| Zero-total explicit approval workflow | NOT TESTED | No safe zero-total browser fixture created |
| Exact category match | PASS | WordPress integration |
| Nonmatch / missing / parent-child denial | PASS | WordPress integration |
| Multi-category exact match | PASS | WordPress integration |
| Public job browse and employer bypass | PASS | Real browser and integration |
| Cross-user protected ownership | PASS | Real browser and integration |
| Direct protected REST write fail-closed | PASS | WordPress integration (HTTP 403) |
| Payment/activation lifecycle notifications | PASS | Integration with intercepted `wp_mail()` |
| Renewal scheduled/started notifications | PASS | Integration with intercepted `wp_mail()` |
| Upgrade, expiry, suspension and admin alerts | PASS | Integration with intercepted `wp_mail()` |
| Deduplication and 300-second retry | PASS | WordPress integration |
| Final-attempt Uncertain and preserved evidence | PASS | Integration plus real admin browser |
| Failed retry capability / scoped nonce | PASS | Real browser |
| Membership filters/details/history | PASS | Real admin browser |
| Notification filters/mobile overflow containment | PASS | Real admin browser |
| Missing/deleted order behavior | PASS | WordPress integration |
| Recovery cursor and bounded batch logic | PASS | WordPress integration and source inspection |
| Distinct-process concurrency load | NOT TESTED | No multi-process load test executed |
| PHP syntax on 8.2 and 8.4 | PASS | CLI lint |
| Real SMTP/provider/inbox delivery | NOT TESTED | Mail deliberately intercepted |
| Cariera registration/login/application/resume | BLOCKED | Vendor components inactive |

The valid integration assertions finished **62 PASS, 0 product FAIL**. The first
retry harness contained two invalid assertions because it selected an upgrade
target for a generic activation event. The engine correctly rejected that event;
the harness was corrected to an original Active membership and all three focused
retry assertions passed. This is a test-fixture correction, not a product waiver.
