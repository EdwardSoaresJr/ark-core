# ARK Complete Hosted - Durable Media Storage Doctrine v1

**Status:** Accepted design - 2026-09-07  
**Companions:** [ark-complete-hosted-hosting-model-v1.md](ark-complete-hosted-hosting-model-v1.md) · Laravel `config/filesystems.php`  
**Scope:** Architecture + Hosted policy. Does **not** move LNP media, change production provider, or resize/migrate compute.

---

## 1. Canonical doctrine

```text
Durable shop media is authoritative in S3-compatible object storage
once it becomes durable.

Local VPS storage is for operational state, temporary staging,
cache, tooling scratch (Imagick / Chromium / OCR), secrets that
must remain on-host, and explicitly short-lived working files.
```

Preferred write path:

```text
upload
  → validate / transform if necessary (may use local temp)
  → S3-compatible object storage
  → object becomes authoritative
  → DB stores disk + object_key (+ mime, size, metadata)
```

**Rejected as default:**

```text
open RO → local VPS
closed RO → migrate to object storage
```

That lifecycle creates dual authority, reopen semantics, and reconciliation debt. Do not introduce it for speculative latency reasons.

---

## 2. Host replaceability (strategic objective)

> Durable shop media must not be trapped on the lifecycle of a particular VPS.

This matters acutely for constrained migrations (no spare destination VPS, no in-place Vultr shrink):

```text
old VPS
  ├── MySQL + runtime
  └── (media no longer the migration blocker)

R2 / S3-compatible durable media
  └── remains authoritative and does not move with the VPS
```

### Existing fleet / migration constraint (binding context)

- LNP currently runs on oversized `vhf-3c-8gb` (`144.202.74.190`) after an in-place resize.
- Desired starter class: `vhf-2c-2gb` (2 vCPU / 2 GiB / 80 GB). Vultr cannot shrink in place.
- **Do not assume** ARK can provision an additional temporary VPS for right-sizing.
- Shadow (`149.28.240.77`) and Platform may be *candidates* for a future temporary authority bridge - **not inspected or authorized here**.
- Eventual right-sizing must preserve a single-authority chain:

```text
current LNP 3/8 authority
  → verified temporary authority
  → recreated/right-sized LNP 2/2 authority
```

At no point may the only authoritative database **or** the only durable-media copy be destroyed.

Off-host durable media makes compute replacement approachable without packing years of photos into every VPS move.

**This mission does not perform that migration.**

---

## 3. Provider abstraction

```text
ARK application
  → Laravel filesystem / S3-compatible disk
  → provider endpoint
```

| Surface | Provider |
| --- | --- |
| **ARK Complete Hosted (default)** | Cloudflare R2 (S3-compatible) - leading candidate |
| **Enterprise / self-hosted** | Customer S3 / MinIO / B2 / other compatible endpoint, or local disk |
| **Dev / single-box** | Local disk remains fully supported |

Core must **not** hard-wire Cloudflare APIs. Provider-specific Hosted wiring belongs in Platform / env / secrets - not application conditionals on `if (r2)`.

`FILESYSTEM_DISK` / `ARK_MEDIA_DISK` select the durable-media disk. R2 is configured as the Laravel `s3` driver with `AWS_ENDPOINT` (and path-style as required).

---

## 4. What belongs where

### Authoritative in object storage (Hosted normal)

- DVI / inspection photos  
- Evidence / RO attachments  
- Customer / portal uploads that are durable  
- Conversation MMS media retained by ARK  
- Scanned documents and durable PDFs that are shop records  
- Dealer quote captures (permanent objects)  
- Learn article media that is shop-owned  
- Approval signatures that are retained as records  

### Remain local (or non-object) by design

| Class | Why |
| --- | --- |
| MySQL data directory | Database engine locality |
| Redis | Ephemeral operational |
| Logs | Operational; rotate |
| Horizon / queue working state | Runtime |
| Imagick / Browsershot / OCR scratch | Absolute FS tooling |
| OIDC keys, QZ, Firebase SA, SIP secrets | Host secrets - not public media |
| Install identity under `storage/app/install` | Platform/install concern |
| Device provision `.cfg` | Generated operational config |
| Upload / transform temp under `sys_get_temp_dir()` | Must clean up |

### Public marketing / brand assets

Shop logos and website featured media may use a **public-readable** object prefix or a separate public disk - still S3-compatible when Hosted - with CDN optional later. Authorization model differs from private operational media; do not collapse them into one ACL.

---

## 5. Shop isolation (shared object account OK)

Compute is dedicated per shop; object storage may be a shared managed service.

**Recommended simplest strong model for Hosted:**

| Layer | Choice |
| --- | --- |
| Account | ARK-managed R2 account (or per-env) |
| Bucket | **One bucket per environment** (e.g. `ark-hosted-prod`) *or* one bucket per shop if ops prefer hard walls - start with **prefix isolation + scoped credentials** |
| Object key | `{installation_uuid}/{collection}/{…}` - UUID, not shop slug |
| Credentials | Prefer **per-installation** access keys limited to that prefix (R2 API tokens / S3 policies) when Platform can issue them; until then, Platform-held credentials with server-side-only access |
| Enumeration | Application never lists sibling installation prefixes for authorization |
| Deletion | Shop cancel → controlled lifecycle job; not casual recursive wipe from UI |

Do **not** rely on human-readable shop names in object keys.

Enterprise: customer supplies endpoint + credentials; same key shape inside their bucket.

---

## 6. Database references

**Current state:** relative `storage_path` / `pdf_path` / `logo_path`; **disk name not persisted**; readers hardcode `local` or `public`.

**Target representation (conceptual):**

```text
disk          - Laravel disk name (e.g. media, public_media)
object_key    - path within that disk (today’s storage_path value)
mime / size / original_name / metadata
```

Do **not** persist provider-specific public HTTPS URLs as authority.

**Schema:** no migration required to *accept* the doctrine. A `disk` column (or equivalent) becomes valuable for **piecemeal local→object migration** and dual-read. Add when cutover implementation starts - not as speculative churn.

Until then: configure `ARK_MEDIA_DISK=local` (default) or `s3` when a shop is cut over wholesale after verified copy.

---

## 7. URL delivery

| Media class | Delivery |
| --- | --- |
| Private operational (evidence, DVI, documents, MMS) | **Auth-gated app routes** today; evolve to **short-lived signed object URLs** (Flysystem `temporaryUrl` / R2 signed GET) after auth check - avoid streaming every large file through PHP when possible |
| Public brand / website | Public object URL or CDN; no secrets in client |
| MMS outbound to Twilio | Keep app signed routes *or* short-lived object signed URLs - provider fetch must not receive long-lived secrets |

Default bias: durable shop media is **not** world-public. Authorization stays under ARK.

---

## 8. Open RO performance

Do **not** invent open/closed storage tiers for latency.

If hot views need help: thumbnails / derivatives, browser cache, CDN, optional local **cache** of hot keys - never a second authority.

Inspection “thumbnails” today are often the full object via the show route - derivative generation is a **future optimization**, not a doctrine blocker.

---

## 9. Processing

```text
upload → local temp (if needed) → transform → put to durable disk → delete temp
```

| Tool | Scratch | Durable result |
| --- | --- | --- |
| GD featured optimizer | public disk variants | object (or public disk) |
| Imagick scan/rotate | `sys_get_temp_dir()` | documents object |
| Browsershot PDF | local path required for write | estimate PDF object |
| OCR | private OCR temp dir | dealer quote object |

Temporary files must have `finally` cleanup (already mostly true). Chromium/Imagick keep a **local scratch plane** forever even when durable media is R2.

---

## 10. Direct-to-object uploads

**Verdict: later.**

Useful when mobile/advisor uploads routinely pressure VPS bandwidth/memory. Not required to adopt R2 for server-side `storeAs` / `put`. Implement after Hosted media cutover is boring.

---

## 11. Backups ≠ primary storage

| Concern | Approach |
| --- | --- |
| Primary media | R2/S3 objects |
| Media protection | Versioning / object lock / lifecycle (Hosted account policy) |
| MySQL backups | Separate prefix or bucket (`…/mysql-backups/{installation_uuid}/…`), encrypted artifacts |
| Disaster recovery | Documented restore: DB backup + object inventory; never “R2 alone is the backup” |

Do not store backups under the same prefix tree as live evidence without a hard naming wall.

---

## 12. Deletion / retention

| Event | Guidance |
| --- | --- |
| Soft-retire evidence/document | Keep object until retention policy says otherwise (**current code already keeps bytes**) |
| Hard delete inspection photo | Delete object (current) |
| Message/attachment delete | Define later - today bytes may orphan |
| Shop cancel | Controlled offline job; grace period |
| Accidental delete | Prefer soft-delete + retention window over immediate object purge |

Do not auto-purge durable objects solely because a row disappears unless domain rules require it.

---

## 13. VPS disk doctrine

Size Hosted VPS tiers for compute/DB/runtime headroom - **not** for years of photos.

80 GB on `vhf-2c-2gb` is for OS, Docker, MySQL, Redis, logs, temp, deploy artifacts, safety margin.

Media growth → object storage cost, not involuntary VPS upgrade.

---

## 14. Migration of existing local media (design only)

```text
1. Inventory keys under storage/app/private (+ public as needed)
2. Copy to object storage under {installation_uuid}/…
3. Verify checksums / spot-read via app
4. Switch ARK_MEDIA_DISK (or dual-read with disk column)
5. Retain local source until soak verified
6. Cleanup local durable media only after explicit authorization
```

Preserve relative keys where possible so `storage_path` rows keep working.

**Not authorized in this mission.**

---

## 15. Security

- No R2/S3 secrets in browser or mobile binaries  
- Server-side credentials via env / Platform secrets / infra backups  
- Private objects: no public ACL  
- Signed URL TTL short; authz checked before minting  
- MIME allowlists remain at upload (EvidenceStore pattern)  
- Treat SVG/HTML uploads as hostile if ever allowed  
- Prefix policies prevent cross-installation listing  

---

## 16. Cost (Hosted, illustrative - not hard-coded)

R2-class economics: storage + Class A/B operations; egress often favorable vs classic S3 for media-heavy apps. Exact cents change; the structural win is **predictable media cost decoupled from VPS RAM/CPU class**.

Track per shop: stored GB, PUT/GET counts, growth rate - Platform telemetry later.

---

## 17. Implementation pointers

| Item | Location |
| --- | --- |
| Disks | `config/filesystems.php` (`local`, `public`, `s3`, `media_disk` selector) |
| Evidence writes | `EvidenceStore`, `InspectionEvidenceStore`, `DocumentStore`, … |
| Private delivery | `*ShowController` + `Storage::response()` |
| Public URLs | `Storage::disk('public')->url()` |
| Hosted env | `ARK_MEDIA_DISK`, `AWS_*` / R2 endpoint |

**Cutover implementation** (future mission): introduce `MediaDisk::name()`, swap hardcoded `'local'` in durable stores, optional `disk` column, signed URL delivery, Platform credential provisioning.

---

## Revision

Revise when Hosted shops prove key layout, signed URL TTL, or isolation model wrong - not because one upload felt slow on a Tuesday.
