# justopen · Link Studio

Self-hosted website links with a customizable two-button landing page. Create a link in the private editor, share it in Instagram DMs, and let visitors choose how to continue. No subscription, click limits, third-party scripts, or analytics cookies on the new landing pages.

## Features

- Any complete HTTP(S) destination, including paths, query parameters, affiliate tags, and fragments.
- Persistent `/l/your-name` links; edit destinations without changing already shared links.
- Two buttons: request an external browser, then open in the current browser, copy the destination, or open a different URL.
- Minimal visitor page: logo/brand, destination hostname, and two buttons. No marketing heading, description, or footer.
- Per-link logo URL, brand, button labels, five colors, and rounded/square buttons; link names are for administration only.
- Live mobile preview and a separate preview page that disables navigation and the countdown.
- Optional automatic opening attempt after 0–300 seconds, with a visitor cancellation checkbox. Blank means disabled.
- Six UTM fields, optionally forwarding UTM supplied on the shared short link.
- Reusable defaults for new links; existing links offer “Vorlage anwenden” to load the latest styling, button actions and opening delay into the editor. Review and save to update the link. Destination, alias, internal name and tracking stay unchanged.
- Private administration, CSRF protection, session expiry, password rotation invalidation, and persistent login rate limiting.
- Deactivate/reactivate links without deleting their configuration. Disabled landing and handoff URLs return HTTP 410.
- Original social app deep links and the Amazon endpoint remain available.
- Stateless Gmail, WhatsApp, Telegram and Google Maps links at `/apps`, with native-app buttons and separate web fallbacks. No database entry per email is needed.

## Local start

Requires Docker Compose. Copy `.env.example` to `.env` and set `JUSTOPEN_ADMIN_PASSWORD` to a unique password of at least 12 characters. Keep `.env` private.

```sh
docker compose up -d --build
```

Open `http://localhost:8088/admin`. The port binds to localhost only. Configure your public HTTPS origin for production. Compose persists `/var/lib/justopen` in a named volume containing SQLite and admin sessions. Do not remove that volume when updating the image.

## Create an Instagram DM link

1. Enter `https://hoerbuch.warsowerk.de/` and optional short name `hoerbuch`.
2. Customize the landing page and its two buttons.
3. Set `utm_source=instagram`, `utm_medium=dm`, and a campaign if needed.
4. Keep automatic opening disabled, or select a delay.
5. Save, copy `https://your-domain/l/hoerbuch`, and send it in a DM.

Existing destination parameters stay byte-identical when no UTM changes are requested. UTM merging preserves unrelated parameter spelling, order, duplicate keys, percent encoding, and fragments. Adding tracking to a signed URL can invalidate its signature.

UTM precedence per key: nonempty saved field, then incoming short-link UTM when forwarding is enabled, then the original destination value. Incoming `url` or unrelated parameters cannot change the destination. Recognized keys: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`. Tracking is added to the main destination, not a separate second-button URL.

## Browser behavior

- Android in detected in-app browsers requests Chrome using an Android intent. The app may show a confirmation; Chrome must be installed. If unavailable, the intent fallback returns to the landing page.
- Instagram on iOS attempts the undocumented `instagram://extbrowser/?url=…` handoff. Threads uses `barcelona://extbrowser/?url=…`. Private schemes can change or be blocked.
- Other detected iOS in-app browsers show instructions to open the app's browser menu. No misleading automatic attempt is made.
- Normal browsers and desktop navigate to the full destination.
- Without JavaScript, ordinary destination links and manual opening instructions remain available; the countdown is disabled. A copy-button destination can be selected from the fallback details.

External opening is not guaranteed on every device or app version. A countdown is not a user gesture and may be blocked. User-agent emulation validates generated URLs, but real Instagram DM tests on current iOS and Android remain necessary. The app does not claim a browser actually opened and never schedules a hidden fallback into the in-app browser.

The Android intent points to `/go/slug` on the configured origin, which resolves the saved destination with a 302. This avoids placing arbitrary destination fragments in intent syntax. Destinations are never fetched server-side. Remote logos load in the visitor's browser without referrers; omit the logo to avoid external image requests.

References: [Android intents](https://developer.chrome.com/docs/android/intents), [in-app device tests](https://github.com/shalanah/inapp-debugger). Opening logic is local; no runtime library or CDN is needed.

## Native app links and Telegram

Open `/apps` (also linked from the private editor) to create a shareable HTTPS link. The app landing always waits for a real button tap: there is no timer-based app launch or hidden web fallback. Unknown parameters cannot replace the generated destinations, and arbitrary custom schemes remain forbidden in the website-link editor.

Examples below use a **placeholder origin**; replace `https://go.example.com` with your deployed instance:

| App | Shareable HTTPS URL | iOS | Android |
| --- | --- | --- | --- |
| Gmail inbox | `https://go.example.com/gmail` | `googlegmail:///` | Gmail launcher intent |
| Gmail message | `https://go.example.com/gmail?mid=receipt%40example.org` | Inbox; optional experimental search link | Inbox; copy the search or use exact web search |
| WhatsApp | `https://go.example.com/whatsapp?phone=491701234567&text=Hallo` | WhatsApp chat | WhatsApp chat |
| Telegram | `https://go.example.com/telegram?username=durov` | Public profile/channel/bot | Public profile/channel/bot |
| Google Maps | `https://go.example.com/maps?q=Brandenburger%20Tor%2C%20Berlin` | Google Maps app | Installed maps-app chooser (`geo:`) |
| Amazon (existing) | `https://go.example.com/amazon?url=https%3A%2F%2Fwww.amazon.de%2Fdp%2FB09V36WL6H` | Amazon web/App Link when supported | Amazon Shopping intent |

Gmail `mid` must be the RFC `Message-ID` (`messageId` from the full n8n Gmail Trigger), **not** Gmail's internal `id` or `threadId`. Surrounding `<…>` are normalized. An optional `account=you%40gmail.com` selects the account for the **web** search only. The device controls the account in the native app.

**Gmail limitation:** there is no reliably supported public message/search deep link for the mobile apps. The requested `googlegmail:///search?q=…` is provided as an explicitly **experimental iOS-only** option, not as a guarantee. Both platforms offer a copyable `rfc822msgid:…` search and an exact Gmail web-search fallback. See [reported Gmail search-scheme limitations](https://stackoverflow.com/questions/76126759/deep-linking-of-gmail-app-on-ios-and-adroid) and [Ghost's Android inbox-launch approach](https://github.com/TryGhost/Ghost/blob/main/ghost/core/core/server/lib/get-inbox-links.ts).

Telegram needs an HTTPS link (in the message or an inline URL button); a bot command does not launch an app on the recipient's phone. See the ready-to-paste [n8n Telegram example](docs/n8n-telegram.md).

App routes do not fetch destinations, send messages, set admin cookies, or write entries to SQLite. Values still appear in shared URLs and may be recorded in browser history, proxy access logs, or messenger previews. Avoid sensitive message bodies and tokens, disable Telegram link previews, and restrict/redact access logs as appropriate. Gmail launch pages do not render the Message-ID in the page title. A successful render/href test does **not** prove that a native app opened; test Telegram → browser → app on physical iOS and Android devices. The installed apps, browser and current OS policy determine the outcome.

## Configuration

| Variable | Purpose |
| --- | --- |
| `JUSTOPEN_BASE_URL` | Exact public origin, e.g. `https://go.example.com`, without a path. Set behind CapRover/TLS proxies so links and secure cookies use HTTPS. |
| `JUSTOPEN_ADMIN_PASSWORD` | Unique admin password of at least 12 characters. |
| `JUSTOPEN_ADMIN_PASSWORD_HASH` | PHP `password_hash()` result; takes precedence over the plain password. |
| `JUSTOPEN_DATA_DIR` | Writable directory outside document root. Docker: `/var/lib/justopen`; otherwise sibling `runtime/`. |

Missing credentials fail closed: admin returns a setup message with HTTP 503; existing public links remain available. Password changes invalidate sessions. Sessions expire after one hour of inactivity. Five failed logins from a socket IP block attempts for 15 minutes. Behind a proxy, users may share that socket IP; forwarded IP headers are deliberately not trusted.

When placing a password hash or a password containing `$` in a Compose `.env`, surround the value with single quotes to preserve literal dollar signs. In CapRover environment fields, enter the raw value without quotes.

New pages use local assets and a nonce-based CSP. Text is escaped, colors are constrained, and text/background contrast must meet 4.5:1. Credentials in destination URLs, non-HTTP schemes, control characters, and backslashes are rejected. Supply internationalized hostnames as punycode and percent-encode spaces in paths.

SQLite keeps deployment small: use **one application replica on one host** with local persistent storage. Upgrade storage when multi-host writers become necessary.

## Routes and compatibility

| Route | Behavior |
| --- | --- |
| `/` | GET redirects to private editor; legacy form POST still works. |
| `/admin` | Private link editor and defaults. |
| `/admin/preview` | Authenticated, CSRF-protected POST preview; does not save. |
| `/l/<slug>` | Public two-button landing, no admin session. |
| `/go/<slug>` | Redirect to stored destination, recognized incoming UTM only. |
| `/healthz` | Database readiness, HTTP 200 JSON. |
| `/social` | Original social link generator. |
| `/apps` | Stateless native-app link forms, no admin session required. |
| `/gmail?mid=…&account=…` | Gmail inbox, copyable search, experimental iOS search and exact web fallback. Both parameters optional. |
| `/whatsapp?phone=…&text=…` | WhatsApp chat; international phone number required, draft text optional. |
| `/telegram?username=…` | Public Telegram profile/channel/bot; username required. |
| `/maps?q=…` | Map search; place/address required. |
| `/api/shorten` | Original social/Amazon API; not arbitrary-URL creation. |
| `/yt/…`, `/ig/…`, `/x/…`, `/fb/…`, `/li/…`, `/sp/…` | Original native-app deep links. |
| `/amazon?url=…` | Original Amazon behavior and affiliate parameters. |

Legacy pages retain upstream styling and app-first behavior. Use the new editor for arbitrary URLs, preserved tracking, and custom landing pages.

## Verification

```sh
php tests.php
php smartlinks-tests.php
php app-links-tests.php
node tests/browser-links.cjs
```

PHP needs PDO SQLite. The Docker image includes it. To run HTTP/browser integration tests against an isolated development instance:

```sh
npm install --prefix . --no-save --package-lock=false playwright
node tests/ui.cjs
node tests/app-links-ui.cjs
```

The browser check uses installed Microsoft Edge; set `TEST_BROWSER_CHANNEL` for another installed Playwright channel. `TEST_BASE_URL` and `TEST_ADMIN_PASSWORD` override local settings. Tests create a link on the target instance: use a development deployment. Screenshots go to ignored `artifacts/`.

See [CAPROVER.md](CAPROVER.md) for deployment, backups, and rollback.

## License

MIT. Based on [unkn0w/justopen.app](https://github.com/unkn0w/justopen.app), originally by Jakub ‘unknow’ Mrugalski. Upstream providers include YouTube, Instagram, X, Facebook, LinkedIn, and Spotify; this fork adds Amazon support.
