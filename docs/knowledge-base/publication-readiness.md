---
title: Publication readiness
description: What was checked before this ARK Core knowledge base is published, and what is still a local preview.
---

# Publication readiness

This report is the state of `docs/knowledge-base` in the working tree that produced it. Nothing here has been deployed. No domain was purchased. DNS was not changed.

## Decision

Keep the knowledge base in this repository, in `docs/knowledge-base`, not in a second documentation repository and not as a VitePress root over the existing `docs/` tree.

| Option | Why it was not chosen |
| --- | --- |
| VitePress rooted at `docs/` | That directory is internal engineering notes, including communications, cloud, and voice material that is not the public product manual. Publishing it wholesale would mix operator doctrine with the public guide. |
| A separate documentation repository | Contributors would update code and docs in different pull requests, and the docs would drift from the Core commit. |
| Inside the Laravel public site | The shop application would gain a documentation runtime it does not need. |

The published website can still use its own hostname later. The source of the pages stays here so a code change and the page that describes it can share a pull request.

VitePress is the framework. It is a dev dependency of `docs/knowledge-base/package.json` only.

## Local preview

```bash
cd docs/knowledge-base
npm install
npm run check:refs
npm run build
npm run dev
```

`npm run dev` serves the authoring server. `npm run preview` serves the production build after `npm run build`. The HTML output is `docs/knowledge-base/.vitepress/dist`.

## Checks that exist

| Check | Where |
| --- | --- |
| Source path comments resolve to files | `npm run check:refs` |
| Internal links | `npm run build` |
| CI on changes under `docs/knowledge-base` | `.github/workflows/knowledge-base.yml` |

## Security review of these pages

Reviewed while writing:

- Examples use `example.test`, fictional names, and the token string `example-token-value-not-real`.
- No production customer rows, private keys, or live passwords.
- `.env.example` names are documented. Secret values are not copied from a live environment.
- Hosted partner APIs (PartsTech and the messaging and phone transports) are named as out of scope. Their request protocols are not reproduced.
- Cloud webhook paths are labeled internal. Signing secrets are not included.
- The Vultr walkthrough in `docs/installation/` is linked as an existing operator note. This site does not add IP addresses or shop hostnames.
- AGPL-3.0-only is stated as the project license and pointed at `LICENSE`. No extra commercial ban was added. ARK Shop is described as a separate service, not as a license condition.

## Not ready, on purpose

- No production URL, analytics, or sitemap hostname. A sitemap without a real origin would publish a fake domain.
- No claim that response JSON is frozen.
- Desk, Tech, and station response bodies are not fully specified.
- Entity-relationship diagrams for the whole schema are not drawn.
- Click-by-click coverage of every staff screen is not written.
- The parent `docs/` tree is not part of this site and should not be rsynced into the published output.

## Before a public launch

1. Run `npm run check:refs` and `npm run build` on the commit you will publish.
2. Read the diff of `docs/knowledge-base` for secrets.
3. Choose a hostname and only then set VitePress `sitemap.hostname`.
4. Deploy the static `dist` directory from a pipeline that does not need the Laravel app.
5. Say which git commit the site was built from.
