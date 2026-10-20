---
title: Maintaining this knowledge base
description: How to keep the ARK Core knowledge base aligned with the source tree.
---

# Maintaining this knowledge base

The site source is `docs/knowledge-base`. It is a VitePress project with its own `package.json`. Do not add VitePress to the shop application's root `package.json` or to `composer.json`.

## When code changes

Update the knowledge base in the same pull request when you:

- Add, remove, or change a route under `routes/api.php`
- Change a staff login rule, capability, or role default
- Add a container binding that other code is expected to replace
- Dispatch a new Laravel event that extenders should hear
- Change installer, backup, or environment requirements
- Change an upload limit or storage disk that clients use

A Blade spacing tweak does not need a doc change. A new public behavior does.

Write the page for the behavior that merged. If the hook does not exist yet, put the idea on [Proposed, not implemented](/tutorials/proposed) or do not mention it.

## Version

These pages describe the commit they ship with. Do not add a version switcher until this repository actually tags documentation branches. The published site can deploy `main` or a release tag later. That deploy is not set up here.

## Checks

From `docs/knowledge-base`:

```bash
npm run check:refs
npm run build
```

`check:refs` requires every page except the home page, this page, the coverage matrix, and the publication report to contain at least one `<!-- source: path/from/repo/root -->` comment, and it requires that path to exist.

`npm run build` fails on broken internal links. That is the link check. There is no separate crawler.

GitHub Actions workflow `.github/workflows/knowledge-base.yml` runs both commands when this directory changes.

Markdown lint is not a separate dependency. Write headings in sentence case, keep lines readable, and let the VitePress build catch broken links. Adding a linter is fine later if a recurring problem shows up. It is not required to publish a correct page.

## Stale pages

A page is stale when its source comment points at a file that no longer contains the behavior the page describes. The path check does not catch that. Reviewers should open the cited file. When you change a cited file, search `docs/knowledge-base` for that path.

## What not to automate

Do not generate the API reference by scraping every controller into a page on each commit until someone owns the prose. A generated dump will document internal methods as if they were promises. Hand-written pages that cite `routes/api.php` age more honestly.
