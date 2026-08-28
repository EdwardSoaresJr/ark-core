# Dragon Assist Bridge v1

**Status:** Realtime delivery correction (local) · arkai install gated  
**Authority boundary:** ARK decides and records truth. Dragon observes, reasons, and assists.

## Architecture

```text
Advisor Saved Work
  → Deterministic Historical Work Recall (unchanged, GET zero-write)
  → POST assist request (durable DragonAssistRequest — persists first)
  → DragonBridgeDispatcher
       ├─ Reverb private channel private-dragon.node.{id}  (NORMAL delivery, ms)
       └─ HTTP pending list on connect/reconnect           (RECOVERY only)
  → arkai dragon-bridge client
       ├─ persistent WSS subscription (Pusher protocol; waits for sub ack)
       ├─ concurrent job tasks (WSS stays alive during Ollama)
       ├─ heartbeat = presence only (not job polling)
       ├─ rare safety reconcile (default 5m) for silent misses
       └─ HTTPS accepted / completed / failed (idempotent)
  → DragonAssistResult
  → advisor poll / optional RO Echo event
```

**Attempt count:** increments only on a real new claim (`pending → dispatched` or node reassignment). Reconnect redelivery to the same node is idempotent and does not burn `MAX_ATTEMPTS`.

## Chosen transport

**Reuse Laravel Reverb for ARK→node push** + **HTTPS for connect / heartbeat presence / ACK / complete**.

Why:

- Coolify already terminates WSS for `/app/{REVERB_APP_KEY}` → Reverb `:9090`
- Public client endpoint: `wss://app.demo-auto.test/app/{REVERB_APP_KEY}` (Pusher protocol)
- Machine auth: `POST /api/dragon/bridge/broadcast-auth` (Bearer DragonServiceToken)
- Pending envelopes on **connect** keep durability when Reverb is down or the node was offline

Heartbeat does **not** redeliver jobs. That was accidental HTTP polling.

## Node identity

`dragon_nodes` linked to `dragon_service_tokens` (hashed at rest).  
Capabilities advertised on connect. Disabled nodes cannot connect or subscribe.

## State machine

`pending → dispatched → accepted → completed | failed`

Idempotent accepted/completed/failed. Max 3 dispatch attempts. Accepted timeout → failed (`stale_accepted`).

## Historical Work Recall Assist

- Deterministic Exact/Likely/Possible **never** changed by Dragon
- Payload: RO id, vehicle facts, template id/title, deterministic recall summary + historical IDs
- No customer phone/email/address
- Result schema prohibits `tier`, `labor_hours`, mutations

## Dragon Service Advisor (v0.1)

- Task type: `service_advisor_rewrite`
- Entry: Narrative card → **Dragon Service Advisor** (single-field rewrite only)
- Modes: Clean Up · Customer Friendly · Concise · Stronger Explanation · Service Advisor Rewrite
- Flow: Generate → preview ORIGINAL / PROPOSAL → explicit Apply → optional Revert
- ARK-side `ServiceAdvisorFactPreservationCheck` rejects proposals that drop measurements/DTCs/sides or invent urgency
- Audit: `dragon_service_advisor_applications` (original, proposal, edited, applied, revert)
- Dragon never writes RO authority — Apply is a staff HTTP write with `RepairOrdersManage`

## Review Estimate Notes (v0.1)

- Task type: `review_estimate_notes`
- Entry: RO review toolbar → **Review Estimate Notes**
- Critique only (gaps / inconsistencies / suggested actions) — no Apply, no field writes
- Context: visit reason + all concern narratives (bounded, no PII)

## UI

Deterministic block first. Quiet “Dragon reviewing…” then Assist card. Never blocks Add Work.

Service Advisor: Narrative modal with field/mode/Generate; no auto-apply.

## Local client

`tools/dragon-bridge/dragon_bridge_client.py`

```bash
cd tools/dragon-bridge
python3 -m pip install -r requirements.txt   # websockets
export ARK_BASE_URL=https://app.demo-auto.test
export ARK_API_TOKEN=drg_...
export DRAGON_BRIDGE_FAKE=1
python3 dragon_bridge_client.py --fake       # WSS + heartbeat presence
# --once = reconcile pending only (no WSS); recovery/debug
```

## Diagnostics

```bash
php artisan dragon:bridge-status
GET /app/dragon/bridge/status   # settings.manage
GET /up/reverb
```

## Explicit non-goals

No Dragon writes to RO/labor/financials · no OEM labor · no voice · no generic chatbot · no remote shell.

## Future production steps (DO NOT EXECUTE UNTIL REALTIME PROVEN)

1. Issue Dragon token: `php artisan dragon:token-issue arkai-assist`
2. On arkai (shop LAN): copy `tools/dragon-bridge` → `/opt/dragon-bridge`, set `/etc/dragon-bridge.env`, run `sudo ./install-systemd.sh`
3. Confirm Reverb `/up/reverb` healthy
4. Verify bridge stays subscribed (logs `reverb_subscribed`) and heartbeats do not redeliver
5. Floor-check RO with Saved Work Exact → Assist appears without waiting ~20s

### systemd (arkai)

```bash
# On production app container — issue token once:
php artisan dragon:token-issue arkai-assist

# On arkai:
sudo rsync -a --delete ~/arksmsv2/tools/dragon-bridge/ /opt/dragon-bridge/
# or scp/rsync from Mac when on shop LAN
sudo /opt/dragon-bridge/install-systemd.sh
sudo nano /etc/dragon-bridge.env   # ARK_API_TOKEN=drg_…
sudo systemctl restart dragon-assist-bridge
journalctl -u dragon-assist-bridge -f
```

Unit: `tools/dragon-bridge/dragon-assist-bridge.service`  
Process maintains: persistent WSS + HTTP control/reconcile + presence heartbeat + Ollama dispatch + reconnect/backoff.
