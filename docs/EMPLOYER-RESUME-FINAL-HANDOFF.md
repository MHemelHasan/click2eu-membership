# Employer Verification and Resume Experience - Final Handoff

## Scope and completion

The approved plan defines three product capabilities plus a final acceptance
gate. The three capabilities are implemented:

1. Employer registration, verification, and Administrator review.
2. Approved-Employer-only Resume Directory.
3. Approved-Employer Contact Candidate on single Resume pages.

The final technical audit also closes Candidate single-Resume access and direct
CV-download authorization. Production acceptance remains gated on the owner-run
licensed Cariera and SMTP tests in `STAGE3-VISUAL-TEST-GUIDE.md`.

## Employer verification data structure

No custom verification table or membership-schema change is used. Data is stored
as WordPress user meta on Employer accounts:

| Key | Meaning |
|---|---|
| `_click2eu_employer_verification_status` | `pending`, `approved`, `rejected`, or `suspended` |
| `_click2eu_company_name` | Sanitized company name |
| `_click2eu_company_registration_number` | Sanitized company registration reference |
| `_click2eu_employer_verification_submitted_at` | UTC submission timestamp |
| `_click2eu_employer_verification_reviewed_at` | UTC review timestamp |
| `_click2eu_employer_verification_reviewed_by` | Reviewing Administrator user ID |
| `_click2eu_employer_verification_admin_note` | Private, sanitized Administrator note |

Missing, empty, or corrupt status on an Employer resolves to `pending`. Candidate,
Administrator, membership, order, renewal, notification, and WooCommerce HPOS
records are not altered by Employer verification.

## Registration and login behavior

- Candidate registration does not display or require company fields and retains
  its previous login behavior.
- Employer registration requires Company Name and Registration Number on the
  server, stores only sanitized values, assigns `pending`, and prevents Cariera
  auto-login.
- After Employer registration the screen says: **Registration submitted! Your
  employer account is pending administrative verification. You can log in after
  an administrator approves your account.**
- Pending login says: **Your employer account is currently pending administrative
  verification. You will be able to log in once your account has been approved.**
- Rejected login says: **Your employer account registration was not approved.
  Please contact Click2EU support for details.**
- Suspended login says: **Your employer account has been suspended. Please contact
  Click2EU support for assistance.**
- Approved Employer login succeeds normally.
- Candidate, Administrator, other-role login, registration, and password reset
  are not blocked by this policy.
- If an approved Employer is changed to Pending, Rejected, or Suspended while
  logged in, its authentication cookie and session are cleared on the next
  request. Protected HTML, AJAX, REST, job/company, Resume, contact, and CV
  download authorization also fail closed.
- Private Administrator notes are never included in a login or frontend message.

Employer lifecycle emails were recommended, not mandatory, by the plan. They are
not implemented, and the UI does not promise an approval email. The Administrator
must communicate the decision through the chosen operational process.

## Administrator review workflow

`WP Admin > Click2EU Memberships > Employer Verification` is restricted to
`manage_options`. It provides status filters/counts, 25-row pagination, Employer
identity/company details, submission/review data, private notes, and nonce-
protected Approve, Reject, Suspend, Set Pending, and Save Note actions. Existing
Employers without meta appear as Pending and are never bulk-approved.

## Centralized authorization result

`Click2EU_Auth::is_approved_employer()` requires a valid Employer user and exact
`approved` status. Administrator access remains separate. The check now governs
protected routes, Resume listing data, REST, vendor AJAX, job/company operations,
Contact Candidate, single Resume viewing, and Resume file download. Candidate
membership does not grant Resume Directory, single Candidate profile, Contact,
or CV download access.

## Resume and contact result

- Shortcode: `[click2eu_resume_directory]` on the existing Resume page.
- Source: published `resume` posts owned by Candidate users with effective Active
  Click2EU membership; no duplicate Candidate database exists.
- Filters: namespaced keyword, location, category, skill, and pagination inputs.
- Candidate email/contact data is absent from cards and frontend contact markup.
- Single Resume continues to use the existing Resume CPT permalink and Cariera
  layout. No new WordPress Page is needed.
- Cariera Invite Candidate is removed only by exact callback and priority.
- Contact Candidate derives both actors and destination server-side and uses
  nonce, idempotency, cooldown, hourly limit, and honest mail outcomes.

## Release and rollback

Deploy only the plugin ZIP to an isolated licensed test site first. Do not import
the LocalWP database over production. Back up the database and current plugin
directory, record the deployed commit, clear caches, and execute the visual test
guide. For rollback, atomically restore the previous plugin directory/ZIP and
clear caches. Do not delete Employer user meta or modify the membership ledger;
the older plugin ignores the new verification meta.

## Acceptance boundary

Source implementation and automated checks can be complete while production
acceptance remains blocked. Required owner evidence is: real Cariera V1/V2/V3,
registration/login/admin transitions, direct access denial, Invite replacement,
Contact email Candidate/Admin copies, Reply-To, rate limiting, responsive UI,
and clean browser/PHP/SMTP logs.
