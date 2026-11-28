# Employer Verification and Resume Experience - Implementation State

## Baseline

- Repository: `MHemelHasan/click2eu-membership`
- Branch: `staging`
- Plugin version: `0.9.2`
- Verified baseline: `c74c3727464616021f7e41a7e874c36dafa03680`
- Previous checkpoint: `aff8762`
- Themes and vendor plugins were inspected only; none were changed.

## Current status

| Stage | Technical status | Browser acceptance |
|---|---|---|
| Stage 0 - audit | Complete | Not applicable |
| Stage 1 - Resume Directory | Complete | Owner acceptance recommended |
| Stage 2 - Employer Verification | Complete after consistency repairs | Pending on licensed test site |
| Stage 3 - Single Resume Contact Candidate | Implemented and statically verified | Pending on licensed test site |
| Final release/security acceptance | Not started | Requires owner visual results |

Overall completion is approximately 90%. Stage 3 source work is complete, but
release acceptance requires owner validation of Cariera V1/V2/V3 and mail.

## Stage 2 completion audit

The pre-Stage-3 audit found and repaired four gaps:

1. Standard WordPress registration had no server-side validation for fallback
   Employer fields. Validation and length limits were added, and fields now
   appear only when the default registration role is Employer.
2. Employer Verification loaded only the newest 200 accounts. It now uses
   paginated `WP_User_Query` results (25 per page) with status counts/filters.
3. `Click2EU_Category_Access::privileged_workflow()` retained a role-only
   Employer bypass. It now requires an approved Employer.
4. `Click2EU_Access::guard_post_actions()` allowed every Employer role through.
   It now bypasses the guard only for approved Employers.

## Stage 3 implementation

- Added `Click2EU_Candidate_Contact`.
- Removes only Cariera's exact `Invite_Candidate::display_invite_candidate`
  callbacks from `single_resume_meta_end` (V1, priority 100) and
  `cariera_candidate_detail_actions` (V2/V3, priority 50).
- Never uses `remove_all_actions()` and preserves unrelated actions.
- Renders one accessible dialog for approved Employers or Administrators on a
  published resume owned by a Candidate with effective Active membership.
- Rejects self-contact and never exposes Candidate email in frontend markup.
- Revalidates authorization and resume state server-side.
- Uses nonce, UUID idempotency, five-minute Employer/resume cooldown, and a
  ten-message-per-hour Employer limit.
- Sends to the Candidate account email, copies WordPress admin, and sets
  Reply-To to the Employer account email.
- Loads new assets only on a singular resume.

## Verification evidence

- PHP 8.2 lint: PASS, 0 failures.
- PHP 8.4 lint: PASS, 0 failures.
- Stage 3 static suite: PASS, 35/35.
- Stage 2 mock suite: PASS, 90/90.
- Stage 1 static suite: PASS, 74/74.
- Stage 1 mock suite: PASS, 28/28.
- Total automated assertions: PASS, 227/227.
- Git whitespace validation: PASS.
- Real Cariera browser/runtime validation: NOT TESTED locally.
- Actual SMTP/inbox delivery: NOT TESTED locally.

## Runtime blocker and next action

Cariera Core 2.0.2 and Addons 1.1.1 deactivate locally because legitimate
license activation is absent. Their license checks were not bypassed. Install
this `staging` checkpoint on the isolated licensed test site and follow
`docs/STAGE3-VISUAL-TEST-GUIDE.md`. Return each failure with its step, layout,
screenshot, console error, and sanitized log excerpt.
