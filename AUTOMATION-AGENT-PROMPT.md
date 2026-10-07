# Production automation prompt

Implement the production automation for the Immaculate Nigeria UK Study Success Masterclass. Use the existing GitHub repository `philipukah/uk-study-masterclass`, branch `main`, deployed at `https://app.immaculate.ng/uk-study-masterclass/`. Preserve the design and deployment path. Never request or print passwords, access tokens, SMTP credentials or webhook secrets in normal chat, and never commit them to GitHub.

## Required outcome

1. Accept registrations through `/uk-study-masterclass/api/register.php`.
2. Write every valid registration to `https://docs.google.com/spreadsheets/d/1aCAF6Wcd5P6Dg0hpN_p4syPpaj98eZgzbKdY1YiuMp4/edit`.
3. Immediately send a confirmation email from `international@immaculate.ng`.
4. Send reminders at T-36h, T-30h, T-24h, T-18h, T-12h, T-6h, T-2h and T-15m before Friday at 8:00 PM WAT. Send a Day 2 reminder after Friday and a profile-assessment follow-up after Saturday.
5. Send browser Pixel and Meta CAPI events with the same `event_id` for deduplication.
6. Redirect successful registrations to `/thank-you/`, which links to `https://chat.whatsapp.com/LXn53SceESW84m0TA1wVv4`.

## Important Meta finding

The current `Composio` data source, ID `1630667418667902`, is an App data source owned by `Philip Ukah Copywriting`. It is not a website Pixel, has never received events and has no integration. Do not use it as the Pixel ID. Have the user select the correct Immaculate Nigeria business portfolio, then create or select a **Web** data source/Pixel. Immediately before generating a CAPI access token, obtain the user’s confirmation. Store the token only in Make’s encrypted secret/connection field.

## Make scenario 1 — registration intake

Create and activate `IMM Masterclass - Registration Intake`:

1. Use Webhooks → Custom webhook named `imm_masterclass_registration`.
2. Require Make webhook API-key authentication. Store the key only in Make and Hostinger `api/config.php`, and send it through the `x-make-apikey` HTTP header.
3. Normalize email to lowercase and phone to E.164 where possible.
4. Search the Google Sheet for the same email and `event_friday`; update an existing row or add a new one.
5. Store: Registered At, First Name, Email, WhatsApp, Event Friday, Event Saturday, Lead Status=`registered`, Reminder Stage=`confirmation_sent`, Last Reminder At, Source, Medium, Campaign, Content, Term, FBCLID, FBP, Event ID, Landing Page, Consent At and Notes.
6. Send an HTML confirmation through Hostinger SMTP from `international@immaculate.ng`. The user must enter the mailbox password in Make’s secure connection form. Use the SMTP host, port and TLS settings displayed in Hostinger hPanel; do not guess them.
7. Send a Meta CAPI `Lead` event to the current Graph API `/PIXEL_ID/events` endpoint using `action_source=website`, `event_time`, `event_source_url` and the incoming `event_id`. Include SHA-256 normalized email, phone and first name plus IP, user agent, `fbc` and `fbp`. Do not send raw contact values to Meta.
8. Return HTTP 200 JSON `{ "ok": true }` only after the Sheet write succeeds. Return an error when the write fails and log it without exposing secrets.

## Make scenario 2 — reminders and segmentation

Create and activate `IMM Masterclass - Reminders & Segmentation`. The current Make plan allows only two active scenarios, so keep all reminder and segmentation routes in this scenario.

Run the regular branch every six hours, aligned so it covers T-36, T-30, T-24, T-18, T-12 and T-6. Add routed weekly checks for T-2h, T-15m, Friday post-class and Saturday post-class if Make’s advanced scheduler supports the exact times. If it cannot, report that the Make plan must be upgraded or use the always-online agent/Hostinger cron for those four sends; do not silently remove them.

Only send when `Reminder Stage` shows the email has not already been sent. Update `Reminder Stage` and `Last Reminder At` immediately after a successful send so reruns are idempotent.

Use these lead-status values: `registered`, `joined_whatsapp`, `reminded`, `attended_day_1`, `attended_day_2`, `no_show`, `assessment_requested`, `qualified`, `client`.

## Email copy requirements

Confirmation subject: `You’re registered: UK Study Success Masterclass`

Confirmation must show the dynamic Friday and Saturday dates, both at 8:00 PM WAT; link to the WhatsApp community; ask the registrant to add `international@immaculate.ng` to contacts; and explain that the live link will arrive before class.

T-36 through T-6 emails must be short and include the exact time, preparation advice and class link. T-2h says the class begins in two hours. T-15m leads with the join link. Friday post-class recaps Day 1 and promotes Day 2. Saturday post-class invites a free UK Study Profile Assessment.

## Tracking and verification

Update `config.js` with the confirmed **website Pixel ID**. The site already emits `PageView`, `ViewContent`, `FormStart`, `Lead`, `CompleteRegistration`, `ThankYouView` and WhatsApp `Contact`. Keep `Lead` and `CompleteRegistration` after successful registration only. Reuse browser `event_id` in CAPI. Preserve UTMs, `fbclid`, `_fbc` and `_fbp` in the Sheet.

Verify in Meta Test Events that PageView arrives from Browser, Lead arrives from Browser and Server, and the shared event ID is deduplicated. Verify one test registration in Sheets, its confirmation email from `international@immaculate.ng`, the thank-you page and WhatsApp button. Delete or label the test lead. Do not launch paid traffic until all checks pass.
