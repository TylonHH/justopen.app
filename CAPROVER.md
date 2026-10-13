# CapRover deployment

Link Studio uses persistent SQLite storage. The old stateless deployment needs a volume and admin credentials before adopting this version.

## New installation

1. Publish the reviewed commit with the supplied GHCR workflow, or deploy source using `captain-definition`.
2. Create a persistent CapRover app with one replica and container HTTP port `80`.
3. Mount a named volume at `/var/lib/justopen`.
4. Set `JUSTOPEN_BASE_URL` to the exact public HTTPS origin.
5. Set a unique `JUSTOPEN_ADMIN_PASSWORD` of at least 12 characters, or `JUSTOPEN_ADMIN_PASSWORD_HASH`.
6. Enable HTTPS and Force HTTPS; do not expose login over plain HTTP.
7. Deploy the tested image by immutable digest or reviewed `sha-*` tag.
8. Verify `/healthz`, `/admin`, legacy links, and real Instagram DM opening on Android and iOS.

The updated `caprover-one-click.yml` configures persistence and credentials for a new app. Publish the image first; the installer cannot build unpublished local code.

## Existing `justopen` app

Read-only inspection on 2026-10-13 found one running instance, SSL on the default subdomain, no custom domain, no persistent volume, and Force HTTPS disabled. The deployed image was `ghcr.io/tylonhh/justopen.app:latest`.

The server's cached image resolved to `ghcr.io/tylonhh/justopen.app@sha256:2faea60e468e0f5ea818afdd3939cb853edd29bc38d1b3c2fc85488fa5f95810`. Recheck the running container image before a production update because the service specification uses a mutable tag.

Before updating the running service:

1. Record its actual deployed image digest and configuration for rollback. Mutable `latest` is not a rollback guarantee.
2. Configure persistence at `/var/lib/justopen`; use one replica and pin to the volume's host in a multi-host cluster.
3. Configure the public HTTPS origin and admin secret; enable Force HTTPS.
4. Deploy the reviewed image in one controlled update.
5. Check legacy `/yt/...`, `/amazon?url=...`, `/healthz`, and the new editor.

No production configuration or deployment was changed during local implementation. The project's `AGENTS.md` requires approval before service-impacting actions on the running instance.

## Backups

Use SQLite's online backup API rather than copying only the main database during writes. Run in the container with read access; copy the protected result off the server:

```sh
php -r '$source=new SQLite3("/var/lib/justopen/justopen.sqlite"); $destination=new SQLite3("/var/lib/justopen/backup.sqlite"); if (!$source->backup($destination)) exit(1);'
```

The official PHP image includes SQLite3 and PDO SQLite. Backups contain all target URLs and link settings. Protect them; keep admin secrets in your password manager. Sessions are not required to restore links.

Restore into an empty, stopped replacement instance, retain `www-data` ownership, and verify readiness and links before switching traffic. Never replace a live database during writes or prune the persistent volume during an image update.

## Rollback

Redeploy the recorded previous image digest. Retain the new volume for a retry. The old image serves social/Amazon routes but does not understand `/l/slug`; newly shared website links need the new implementation.

## Local review

```sh
docker compose -p justopen-preview up -d --build
```

Configure a private `.env`, then open `http://localhost:8088/admin`. The localhost binding does not publish a preview on Netcup.

LinkStack or OpenReply can point to `/l/slug` if desired. Only supported incoming UTM fields are forwarded when enabled.
