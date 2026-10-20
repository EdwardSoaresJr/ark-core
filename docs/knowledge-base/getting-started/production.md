---
title: Production
description: What a public ARK Core deployment has to cover beyond a local Compose demo.
---

# Production

<!-- source: docs/installation/vultr.md -->

A local `docker compose up` on port 8088 is a shop only for the machine that can open it. A shop other people use needs a host, HTTPS, backups, and `APP_DEBUG=false`.

This page is the production shape. It does not buy a server, set DNS, or deploy anything.

## Recommended shape

1. A Linux host with enough RAM for MySQL, Redis, PHP, Horizon, Reverb, and the scheduler. Existing operator notes use 1 GB as the starter size with swap, and 2 GB when you want room for photos and updates.
2. Docker Compose from this repository, built on that host.
3. A reverse proxy on 443 that terminates TLS and forwards to the app. The repository's cloud walkthrough uses Caddy in front of Compose. Any proxy that sets `X-Forwarded-Proto` is compatible with `trustProxies(at: '*')` in `bootstrap/app.php`.
4. `APP_URL` set to the public `https://` origin. When `APP_URL` starts with `https://`, Core forces the HTTPS scheme for generated URLs.
5. Firewall: 22 for SSH, 80 and 443 for the proxy. Do not leave shop data on port 8088 without TLS.

The longer Ubuntu walkthrough that already lives in the repository is `docs/installation/vultr.md`. It is an operator guide for a small cloud VM. It is not a requirement to use that vendor.

## Immutable environment

On Compose, bootstrap secrets are generated onto the secrets volume. Do not hand-edit those secrets to "fix" a running shop. Replacing `APP_KEY` makes existing encrypted values unreadable.

Set `APP_ENV=production` and `APP_DEBUG=false` for a public host.

## Processes

The Compose app container runs nginx, PHP-FPM, Horizon, Reverb, and the scheduler. If you are on the PHP path instead, you must supervise those processes yourself. A single `php artisan serve` is not a production server.

## What production still does not add

A public URL does not enable customer texting, hosted phones, in-app card capture, or the PartsTech catalog. Those remain ARK Shop. Put your own SMTP credentials in the environment if this server should send mail through a provider you control.

## Updates

Pull the commit you intend to run, rebuild, and migrate. The procedure and the backup boundary are on the [next page](/getting-started/updates-and-backup).
