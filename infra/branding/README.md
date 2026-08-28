# ARK Ecosystem Branding

**Source of truth:** `public/assets/ARK_SMS_FINAL_DROP_IN_PACK/`

**Ownership doc:** `docs/branding/ownership.md`

## Scripts

| Script | Purpose |
|--------|---------|
| `sync-ecosystem-favicons.sh` | Dev: copy pack → BookStack theme (commit theme after) |
| `deploy-bookstack-branding.sh` | Production ARKademy theme + `apply-branding.sh` |
| `deploy-arkify-branding.sh` | Control plane Arkify favicons + layout patch |
| `apply-arkify-branding.sh` | Run on control plane (also called by guardrails cron) |
| `verify-ecosystem-branding.sh` | HTTP verification for all surfaces |

## ARK V2

Runtime: `App\Support\Branding\Branding` → `partials/branding/_favicons.blade.php`

## ARKademy

```bash
./infra/branding/deploy-bookstack-branding.sh
```

Re-run after BookStack image upgrades if branding settings reset.

## Arkify

```bash
./infra/branding/deploy-arkify-branding.sh
```

Survives Coolify upgrades via `/data/coolify/custom/ark-branding/` + guardrails cron.

## Public Surface (shop host)

**Exception:** `demo-auto.test` is a placeholder shop domain for favicon/OG — served by ARK V2 Public Surface (not ARK-WEB / Botble).

## Verify

```bash
chmod +x infra/branding/*.sh
./infra/branding/verify-ecosystem-branding.sh
```

Doctrine: `docs/branding/ecosystem-identity.md`
