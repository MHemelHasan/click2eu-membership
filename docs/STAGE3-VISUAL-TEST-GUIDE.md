# Stage 2 and Stage 3 Visual Test Guide

Use only isolated test accounts, resumes, orders, and email addresses. Keep
production untouched and do not use real Candidate data.

## 1. Prepare the test site

1. Back up the test site and confirm it is not production.
2. Install the current `staging` build and activate Click2EU Membership.
3. Confirm its version is `0.9.2`.
4. Activate legitimately licensed Cariera Core 2.0.2 and Cariera Addons 1.1.1,
   WP Job Manager, WooCommerce, Cariera, and Cariera Child.
5. Configure test SMTP and a test WordPress Administration Email.
6. Create isolated accounts: two Candidates, one pending Employer, one approved
   Employer, and one Administrator.
7. Give Candidate 1 an effective Active membership and published resume. Give
   Candidate 2 an inactive/expired membership and published resume.
8. Open browser Developer Tools with Console and Network visible.

## 2. Verify Stage 2 registration and approval

1. While logged out, select Candidate during registration. Confirm company
   fields are not required.
2. Select Employer. Confirm Company Name and Registration Number appear and are
   required.
3. Submit with either field empty. Confirm a clear validation error.
4. Submit valid test values. Confirm the Employer becomes Pending and is not
   automatically logged in.
5. Try to log in as Pending. Confirm login is blocked with a review message.
6. As Administrator, open **Click2EU Memberships > Employer Verification**.
   Confirm company details, status, private note, and actions display correctly.
7. Approve the Employer. Confirm login and approved-Employer access now work.
8. While that Employer is logged in, suspend it as Administrator. Refresh the
   Employer page and confirm privileged access is removed.
9. Confirm Rejected and Set Pending also block login/access, and private notes
   never appear on the frontend.
10. If more than 25 Employers exist, check page 2 and every status filter.

## 3. Verify Stage 3 on V1, V2, and V3

Repeat these steps for every Cariera single-resume layout: V1, V2, and V3.

1. As the approved Employer, open Candidate 1's single-resume page.
2. Confirm **Invite Candidate** is absent.
3. Confirm exactly one **Contact Candidate** button appears. Bookmark, Download
   CV, expiry, edit, and other unrelated actions must remain unchanged.
4. Open the dialog. Confirm focus enters it, Escape/Close works, labels are
   clear, and Employer name, company, and reply email are correct.
5. Inspect Elements and View Source. Search for Candidate 1's email. It must not
   appear in HTML, data attributes, or JavaScript.
6. Enter a test subject/message and submit once. The result may say WordPress
   accepted it for sending; it must not promise inbox delivery.

## 4. Verify mail and abuse controls

1. Confirm the Candidate test inbox receives the message.
2. Confirm the WordPress Administration Email receives a copy.
3. Reply to the Candidate email; Reply-To must target the Employer account.
4. Submit again immediately for the same Employer/resume. Confirm cooldown.
5. Double-click or replay the same request. Confirm no duplicate processing.
6. After cooldown, exceed ten attempts in one hour using isolated test resumes.
   Confirm the next attempt is blocked. Reset only test transients afterward.
7. If WordPress accepts mail but the inbox does not, inspect SMTP logs and mark
   this as mail delivery failure, not a successful test.

## 5. Verify denied states

Each state below must have no Contact Candidate access, or its submission must
be rejected server-side:

| Viewer/state | Expected result |
|---|---|
| Logged-out guest | Denied |
| Candidate | Denied |
| Pending Employer | Denied |
| Rejected Employer | Denied |
| Suspended Employer | Denied |
| Approved Employer contacting own resume | Denied |
| Administrator | Allowed |

As the approved Employer, open Candidate 2's inactive/expired membership resume.
Contact Candidate must be unavailable.

## 6. Security and responsive checks

1. Change the submitted resume ID to an invalid or different resume. Confirm the
   server rejects it.
2. Remove or alter the nonce. Confirm the security check rejects it.
3. Check desktop, tablet, and mobile widths. The dialog must remain readable,
   scrollable, closable, and inside the viewport.
4. Confirm there are no new JavaScript console, PHP fatal, or debug-log errors.

## 7. Send back the results

Report PASS or FAIL for each section. For failures include the stage/step,
Cariera layout, viewer role/status, page URL, exact message, screenshot, console
error, and sanitized WordPress/PHP/SMTP log lines. Never send passwords, license
keys, cookies, Candidate email addresses, or other personal data.
