# UK Study Success Masterclass

Responsive Meta-first webinar funnel for Immaculate Nigeria.

## Deployment

- Entry file: `index.html`
- Build command: none
- Hostinger branch: `main`
- Hostinger install path: `public_html/uk-study-masterclass`

## What is included

- `index.html` — landing page, recurring Friday/Saturday dates, countdown, attribution capture and registration flow
- `thank-you/index.html` — confirmation page, WhatsApp community CTA and welcome-video placeholder
- `api/register.php` — same-origin endpoint with validation, bot trap, rate limiting and Make forwarding
- `config.js` — public endpoint and website Pixel configuration
- `api/config.example.php` — server-only Make webhook configuration template
- `AUTOMATION-AGENT-PROMPT.md` — implementation brief for Sheets, Hostinger email, reminders and Meta CAPI

## Private server configuration

1. Copy `api/config.example.php` to `api/config.php` on Hostinger.
2. Add the Make custom webhook URL and a long random shared secret.
3. Never commit `api/config.php`; it is already ignored.
4. Add the confirmed WEBSITE Pixel ID to `config.js`. Do not use a Meta App ID.

The form redirects only after Make confirms success. Browser `Lead` and `CompleteRegistration` events reuse the same `event_id` sent to Make for CAPI deduplication.

## Google Sheet

`https://docs.google.com/spreadsheets/d/1aCAF6Wcd5P6Dg0hpN_p4syPpaj98eZgzbKdY1YiuMp4/edit`

## Current Meta finding

Meta currently shows `Composio` (ID `1630667418667902`) as an **App** data source owned by `Philip Ukah Copywriting`, not a website Pixel. It reports zero events and no integration. Do not paste that App ID into `config.js`; select the correct Immaculate Nigeria business and create or select a Web data source first.
