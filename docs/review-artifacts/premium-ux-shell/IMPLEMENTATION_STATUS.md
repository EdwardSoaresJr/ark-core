# Premium UX implementation status

## Merged dirty-state fix

| Item | Value |
|------|--------|
| PR | [#293](https://github.com/EdwardSoaresJr/ark-core/pull/293) |
| Merge SHA | `ea1ac6d1ccba03ad6f52a1400d92cc79db9e9610` |
| Required CI | gitleaks, lint, security, targeted, focused, communications-boundary, package — **SUCCESS** |
| Regression / `pest` job | **SKIPPED** — `regression.yml` only runs the full suite on PRs labeled `full-regression` (or schedule / workflow_dispatch). This PR was not labeled; focused Tests workflow covered the change. |

Race protections preserved (hydration touch-skip, submit snapshot adopt, generation settle, failed-save dirty, flush/clear on navigate, modal discard).

## Implementation PR

| Item | Value |
|------|--------|
| PR | [#294](https://github.com/EdwardSoaresJr/ark-core/pull/294) |
| Head | `3e585b09` (update after CI) |
| Base | `main` @ `ea1ac6d1` |

### Shared components changed

- `shell/tabler.blade.php` — global left chrome (hamburger, fold, search)
- `display-theme-boot.blade.php` — fold preference before paint
- `config/ark_workspace_tabs.php` — empty `docked_contextual`; vehicle show path
- `WorkspaceTabSupport` — vehicle boot → `operations.vehicles.show`
- Customer show identity + Vehicles label + Create RO wording
- New `VehicleShowController` + `vehicles/show.blade.php`
- RO identity Create RO labels
- `TABLER.md` shell/action contract

### Local verification

- Pest: VehicleShow, WorkspaceTabs, WorkspaceCommandLanguage, TablerShell — **47 passed**
- Dirty JS units — **17 passed**

### Staging

- `alpha.staging.arksms.com/up` and `demo.staging.arksms.com/up` → 200
- ark-stancl **#379** still **OPEN** (field routing / Maps)
- **No staging deploy** in this session — avoid colliding with routing workstream
- SaaS Core pin PR **not opened** until #294 merges

### Production boundaries

- Do not change `v1.0.0-rc.1`
- Do not deploy production from this authorization
- Rollback: revert #294 merge commit; dirty-state remains via #293

## Outstanding

- Calendar/Settings/Job Board action packing polish (further density)
- Live browser screenshot pack against Herd once CI green
- Advisor vehicle edit still deep-links to customer hub modal (intentional reuse)
