# Job Board List | Cards — final acceptance (PR #253)

**PR:** [#253](https://github.com/EdwardSoaresJr/ark-core/pull/253) (draft until authorized)  
**SHA:** _(recorded on push)_  
**Supersedes:** `38c8d9c9` (#252 merged)

No merge / deploy / SaaS pin / Sites / migrations / production data from this session.

---

## Preference scope (browser-level)

| Item | Finding |
| --- | --- |
| Storage | Cookie `ark_job_board_layout` only — **not** an account/user-column preference |
| Path | `/` |
| Domain | Host-only when `ARK_ECOSYSTEM_COOKIE_DOMAIN` is empty (matches other ecosystem cookies) |
| Encryption | Cookie is on the EncryptCookies **except** list so the client switcher can write a plain value that the server can read |
| Query | `?view=list\|cards` wins and queues the cookie; legacy `?view=board` → cards |
| Switcher | Updates cookie **and** `history.replaceState` view param so reload keeps the choice without a full navigation |
| Shop default | Follow-up (not in this PR) |
| Other surfaces | Cookie name is Job Board–specific; no effect on schedule/estimate preferences |

---

## Switching / filters (Playwright)

| Check | Result |
| --- | --- |
| List → Cards without page reload | Yes (`navigationsDuringSwitch: 0`) |
| Cards → List without page reload | Yes |
| Lane preserved | Yes (`waiting_approval`) |
| Search preserved | Yes (`q=SYN`) |
| Same RO ids both layouts | Yes |
| Activity / RO / customer deep links | Preserved (`#builder`, customer hub) |
| Reload remembers Cards | Yes (after URL+cookie sync fix) |

Evidence: `SWITCHER_ACCEPTANCE_EVIDENCE.json`

---

## Mobile @ 390

| Check | Result |
| --- | --- |
| Auto default | Cards (`layout=auto`, `effective=cards`) |
| Explicit List | Usable mobile rows; desktop table `display:none`; `scrollWidth=390` |
| Switch back to Cards | Works |

---

## Watch on Cards

| State | Presentation |
| --- | --- |
| Watch | Muted text; **no** yellow `card-status-start` |
| Attention | Yellow edge accent |
| Critical | Red edge accent |

Classifier unchanged.

---

## Screenshots

| File |
| --- |
| `screenshots/switcher-list-1366.png` |
| `screenshots/switcher-cards-1366.png` |
| `screenshots/switcher-cards-1440.png` |
| `screenshots/switcher-cards-1920.png` |
| `screenshots/switcher-cards-390.png` |
| `screenshots/switcher-list-390.png` |
| `screenshots/switcher-cards-dense-attention-1366.png` |

---

## Remaining blockers

None for merge authorization from this acceptance pass. Integration owner still owns staging pin + combined Sites release cutover.
