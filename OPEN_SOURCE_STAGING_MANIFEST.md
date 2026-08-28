# ARK Public Staging Manifest

**Created:** 2026-08-28  
**Source foundry:** `/Users/edwardsoares/Herd/arksmsv2` @ `a8d1d5e6de1d5f87cdf4b79455745f6e845647d4`  
**Staging path:** `/Users/edwardsoares/Herd/ark-public-staging`  
**Status:** LOCAL STAGING ONLY — not published · no GitHub remote · license undecided

## Intent

Private `arksmsv2` = foundry / proving ground.  
This tree = sanitized product snapshot for a future public repo with **new Git history**.

Rule: **separate code from proprietary fuel** — do not hollow the architecture.

## Provisional Dragon boundary (staging)

**Open Dragon runtime** (agent loop, tools, Level 3 memory engine, BYO provider).  
**Exclude Dragon fuel** (arkai import dump, ARKademy/shop knowledge packs).

License and final Hosted Dragon commercial boundary remain undecided.

## Verification

- `composer install` — succeeded
- Focused tests (2026-08-28): 19 run · 17 passed · 2 failed (missing view cache path on HTTP cookie test; Dragon settings inspect expected 200 got 302 — staging env debt, not architecture)
- Full suite / Docker boot — **not yet run** (follow-up before publish)
- MySQL migrate+seed stranger boot — **not yet proven** on this machine from staging alone

## INCLUDED (categories)

- Laravel application (`app/`, `bootstrap/`, `config/`, `routes/`, `resources/`, `public/` assets)
- Migrations + synthetic-oriented seeders (Demo Auto Repair identity)
- Tests + Pest config
- `apps/ark_desk`, `apps/ark_tech`, `apps/advisor_station` (clients; defaults neutralized)
- Dragon **code** under `app/Ark/Dragon/**`
- Labor-guide **import interface** + `rte/schema.md` / README (no CSV fuel)
- `.env.example` with placeholder `demo-auto.test` domains
- Dockerfile, composer.lock, package-lock.json
- Engineering/docs doctrine (residual proving-ground mentions may remain in narrative docs)
- `.cursor/rules` doctrine (production deploy rules stripped)

## EXCLUDED (categories)

| Exclusion | Reason |
| --- | --- |
| `.git/` history | Start fresh public history |
| `rte/*.csv`, `rte/*.sql`, flat labor files | Licensed third-party automotive fuel |
| `database/data/dragon-arkai-import-v1.json` | Shop knowledge + employee PII |
| `docs/shop-excellence/cecil-bullard`, `lucas-underwood`, `sources.yaml`, `lugs-n-plugs`, `private/` | Third-party training synthesis / shop private |
| `infra/coolify/**`, production deploy scripts, backups | LugsNPlugs/Coolify ops |
| `docs/deployment/**`, growth GSC screenshots | Production ops / analytics |
| `.env`, `.env.production`, credential backups, OIDC PEMs | Secrets |
| `vendor/`, `node_modules/`, build artifacts, sqlite DBs | Regenerable / local |
| `docs/open-source/` audit (kept in foundry) | Audit belongs with private readiness work |

## Sanitizations applied in staging

- Owner PII in seeders → synthetic Alex Rivera / `example.test` / `7195550199` / fake VIN
- Shop identity seeder → Demo Auto Repair / `719-555-0100` / `demo-auto.test`
- Domain defaults `*.lugsnplugs.com` → `*.demo-auto.test` across config/tests/apps (majority)
- Production IPs / Coolify app ids / Twilio SID placeholders neutralized or removed with ops trees
- Stock Laravel README replaced with ARK public README
- Labor `rte/README` rewritten: fuel not redistributed

## Known residual debt (scrub before publish)

- Some docs still narrate LugsNPlugs / Autorepairkeeper as historical context
- `config/public_seo.php` and public marketing copy may still feel shop-specific — needs second pass or synthetic SEO pack
- Preline Fair Use + OSL/Square license review unfinished
- LICENSE / SECURITY.md / CONTRIBUTING not added (intentional until counsel)
- Storage preference keys renamed `ark.shop_glass.*` in Flutter — verify no broken migrations of local prefs (client apps)
- Demo logo is former shop asset renamed file — replace with ARK-owned neutral mark before publish
- Full `php artisan test` + stranger MySQL boot not certified

## Sync process (later)

Mechanical private → public export: allowlist rsync + sanitizers + CI gate. Not built yet.

## Do not

- Create GitHub public repo from this tree until human inspection of this manifest
- Push anywhere yet
- Claim license
