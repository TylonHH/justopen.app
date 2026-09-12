# CapRover deployment

This fork is prepared for container deployment on CapRover.

## Architecture

The app is stateless. It does not require a database, Redis, or persistent volumes.

- Web app: Apache + PHP
- Image: `ghcr.io/tylonhh/justopen.app:latest`
- Container port: `80`
- Recommended public hostname: `go.example.com`

## One-click install

Use `caprover-one-click.yml` in CapRover's One-Click Apps/Databases screen.

After deployment:

1. Enable HTTPS.
2. Enable Force HTTPS.
3. Point a short domain such as `go.example.com` at the app.
4. Make the GHCR package public if CapRover cannot pull it anonymously.

## Amazon / in-app browser escape

For Amazon links use:

```text
https://go.example.com/amazon?url=https%3A%2F%2Fwww.amazon.de%2Fdp%2FB0EXAMPLE%3Ftag%3Dyourtag-21
```

The complete destination URL is preserved, including an Amazon Associates `tag` and other query parameters.

Behavior:

- Android: attempts to open the Amazon Shopping app with an Android `intent://` URL and falls back to the original HTTPS Amazon URL.
- iOS inside Instagram/Facebook/TikTok and other detected in-app browsers: shows a short instruction to use the browser menu and open the page in Safari. iOS does not offer a reliable standards-based way for a webpage to force itself out of another app's embedded browser.
- iOS in Safari / normal browsers: continues to the original Amazon HTTPS URL, allowing normal Universal Link behavior when supported by the device/app.
- Desktop: redirects directly to the original Amazon URL.

This endpoint intentionally only accepts known Amazon hosts to avoid turning the service into a public arbitrary open redirect.

## LinkStack

Keep LinkStack as your public link-in-bio / landing page. Point selected LinkStack buttons at this deep-link service instead of directly at Amazon:

```text
LinkStack -> go.example.com/amazon?url=... -> Amazon app/system browser
```

## OpenReply

OpenReply can keep its own tracking redirect in front:

```text
Instagram DM -> OpenReply tracked link -> go.example.com/amazon?url=... -> Amazon
```

This preserves OpenReply click analytics while the final hop handles the in-app-browser escape.
