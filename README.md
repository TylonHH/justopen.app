# justopen · Link Studio

Self-hosted website links with a customizable two-button landing page. Create a link in the private editor, share it in Instagram DMs, and let visitors choose how to continue. No subscription, click limits, third-party scripts, or analytics cookies on the new landing pages.

## Features

- Any complete HTTP(S) destination, including paths, query parameters, affiliate tags, and fragments.
- Persistent `/l/your-name` links; edit destinations without changing already shared links.
- Two buttons: request an external browser, then open in the current browser, copy the destination, or open a different URL.
- Per-link brand, heading, description, logo URL, button labels, five colors, and rounded/square buttons.
- Live mobile preview and a separate preview page that disables navigation and the countdown.
- Optional automatic opening attempt after 0–300 seconds, with a visitor cancellation checkbox. Blank means disabled.
- Six UTM fields, optionally forwarding UTM supplied on the shared short link.
- Reusable defaults for new links; changes to defaults do not change existing links.
- Private administration, CSRF protection, session expiry, password rotation invalidation, and persistent login rate limiting.
- Deactivate/reactivate links without deleting their configuration. Disabled landing and handoff URLs return HTTP 410.
- Original social app deep links and the Amazon endpoint remain available.

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
| `/api/shorten` | Original social/Amazon API; not arbitrary-URL creation. |
| `/yt/…`, `/ig/…`, `/x/…`, `/fb/…`, `/li/…`, `/sp/…` | Original native-app deep links. |
| `/amazon?url=…` | Original Amazon behavior and affiliate parameters. |

Legacy pages retain upstream styling and app-first behavior. Use the new editor for arbitrary URLs, preserved tracking, and custom landing pages.

## Verification

```sh
php tests.php
php smartlinks-tests.php
node tests/browser-links.cjs
```

PHP needs PDO SQLite. The Docker image includes it. To run HTTP/browser integration tests against an isolated development instance:

```sh
npm install --prefix . --no-save --package-lock=false playwright
node tests/ui.cjs
```

The browser check uses installed Microsoft Edge; set `TEST_BROWSER_CHANNEL` for another installed Playwright channel. `TEST_BASE_URL` and `TEST_ADMIN_PASSWORD` override local settings. Tests create a link on the target instance: use a development deployment. Screenshots go to ignored `artifacts/`.

See [CAPROVER.md](CAPROVER.md) for deployment, backups, and rollback.

## License

MIT. Based on [unkn0w/justopen.app](https://github.com/unkn0w/justopen.app), originally by Jakub ‘unknow’ Mrugalski. Upstream providers include YouTube, Instagram, X, Facebook, LinkedIn, and Spotify; this fork adds Amazon support.
