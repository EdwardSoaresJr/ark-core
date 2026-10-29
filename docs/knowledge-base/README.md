# ARK Core knowledge base

This directory is the publishable documentation site for ARK Core. It is a [VitePress](https://vitepress.dev/) project. It is not loaded by the Laravel application and it is not a Composer or root npm dependency.

The rest of `docs/` is internal engineering material. Do not treat those notes as this site, and do not point VitePress at the parent `docs/` directory. That tree includes operational notes that are not the public knowledge base.

## Run it locally

From this directory:

```bash
npm install
npm run dev
```

Other checks:

```bash
npm run check:refs   # <!-- source: repo/path --> comments must exist
npm run build        # static site, including broken-link checks
npm run preview      # serve the built site
```

The built files land in `.vitepress/dist`. Publishing that output, buying a domain, and configuring DNS are separate decisions.

## Why it lives in this repository

Documentation that ships next to the code can be reviewed in the same pull request as the change. The published website can still use its own domain and deploy pipeline. A dedicated documentation repository would drift from the Core commit it describes.

This tree documents the Core commit it belongs to. It does not carry a fake version switcher for releases that are not tagged here.
