# Production Voice Cutover — LugsNPlugs

**Status:** Active  
**Shop:** LugsNPlugs production (`ark-lugsnplugs-production`)  
**Question every task must answer:** Does this move one more real customer call from the old phone system to ARK without risking the business?

LugsNPlugs production **is** the cutover environment. There is no separate pilot stack, parallel phone system, or LAN-only proof-of-concept path for voice.

---

## Architecture (target)

```text
Customer PSTN
      │
      ▼
Twilio (PSTN + business number)
      │
      ├── Rollback path ──► Programmable Voice webhook ──► ARK (TwiML ring group)
      │
      └── Cutover path ───► Elastic SIP Trunk ──► ark-asterisk (voice.demo-auto.test)
                                    │
                                    ▼
                              Poly VVX / desk endpoints
                                    │
                                    ▼
                              AMI bridge ──► SessionEvents ──► CallSession ──► Conversation
```

**ARK owns:** CallSession, Conversation, CommunicationDevice, SessionEvents, shop UI.  
**Asterisk owns:** SIP registration, media bridge, dialplan execution.  
**Twilio owns:** PSTN until fully replaced — trunk is the incremental handoff point.

**Media (answered recording + voicemail):** Asterisk dialplan writes WAV files to a shared recordings volume. On hangup, ARK ingests metadata into `call_sessions.recording_*` / `voicemail_*` via the `ended` call-event path and optional `POST /voice/call-media` from the AMI bridge. Playback and call intelligence reuse the existing Twilio UI pipeline with source-aware `ark-voice://` URLs.

```text
Inbound PSTN → Asterisk [ark-inbound-router]
  ├─ closed / after-hours → Record → vm-{UNIQUEID}.wav
  └─ open → Dial → MixMonitor → call-{UNIQUEID}.wav (when record_inbound_calls)
Hangup → AMI bridge → POST /voice/call-events (ended)
      └→ POST /voice/call-media (when WAV present)
ARK → call_sessions media fields → existing playback + Whisper pipeline
```

Production requires **`VOICE_RECORDINGS_PATH`** on `arksms` mounted to the same host volume as `ark-asterisk` (`ark-voice-recordings` → `/var/spool/asterisk/ark-recordings`).

---

## Production runtime (Coolify)

| Service | Role |
|---------|------|
| `arksms` | Product — webhooks, SessionEvent ingress, shop workspace |
| `ark-asterisk` | Voice transport — PJSIP, dialplan, Twilio trunk termination |
| `ark-asterisk-bridge` | AMI → HTTPS → ARK device + call events |
| `ark-mysql` / `ark-redis` | Shared operational data |

Phones register to the **deployment SIP registrar** (`VOICE_SIP_REGISTRAR`) — not a product URL. HTTP capabilities use `SHOP_BASE_URL/voice/*`. See `docs/platform/shop-identity-v1.md`.  
Integrator docs: `infra/coolify/asterisk/RUNBOOK.md` · Twilio trunk: `infra/coolify/asterisk/twilio-trunk-cutover.md`

Local-only Asterisk compose (`infra/pilot-shop/asterisk/`) is for developer transport debugging — not LugsNPlugs production.

---

## Cutover sequence (incremental, rollback at every stage)

| Stage | What | Rollback |
|-------|------|----------|
| **0** | SessionEvent ingress on production Twilio path | Already live |
| **1** | `ark-asterisk` healthy on Coolify | Stop service; Twilio unchanged |
| **2** | VVX registers → `CommunicationDevice` Connected | Phone stays on old system; ARK device offline |
| **3** | Twilio Elastic SIP Trunk → `voice.demo-auto.test` (test origination only) | Delete trunk test; no customer impact |
| **4** | One real inbound via trunk → VVX; SessionEvents in ARK | Repoint trunk; webhook path unchanged |
| **5** | Business number origination → trunk (parallel observation) | Twilio Console: number Voice URL → webhook (< 5 min) |
| **6** | One week notebook — car count, missed calls, advisor friction | Revert Stage 5 |
| **7** | Expand devices, ring policy, then retire Twilio-only desk SIP | Restore Twilio SIP domain registration |

**Do not skip stages.** Rollback never requires an ARK deploy — only Twilio routing and optional Coolify service stop.

---

## Operator surfaces (unchanged doctrine)

| Surface | Question |
|---------|----------|
| Shop · Communications | Can my shop communicate right now? |
| Person | Can this person communicate? |
| Device | Can this device communicate? |
| Conversation / Attention | What is happening with this customer? |

No PBX vocabulary on operator UI. Extensions and trunks are transport configuration only.

---

## Notebook (weekly during cutover)

| Week | Inbound via trunk | Missed / failed | Devices Connected | Rollback used? | Notes |
|------|-------------------|-----------------|-------------------|----------------|-------|
| | | | | | |

---

## Explicit non-goals during cutover

- BLF, paging UI, transfers UI, auto-provisioning theater
- Replacing Twilio SMS/MMS (separate channel; stays on Conversation)
- Building FreePBX admin inside ARK
- Second phone system or shop-LAN Asterisk for production validation

---

## Companions

- `infra/coolify/asterisk/RUNBOOK.md` — deploy and verify `ark-asterisk`
- `infra/coolify/asterisk/twilio-trunk-cutover.md` — Twilio trunk + rollback steps
- `docs/communications/ark-voice-phase1-spec.md` — SessionEvent + device authority
- `.cursor/rules/ark-telephony-roadmap.mdc` — Conversation remains authority for messages
