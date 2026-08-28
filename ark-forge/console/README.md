# ARK Console v0.1

Local OpenAI client for **ARK Bridge**. Breaks the copy/paste loop: the model calls Forge capabilities through Bridge and answers from live workstation data.

```
ARK Console → OpenAI Responses API → Tool Dispatcher → ARK Bridge (:9471) → Forge Core
```

This is **not** ChatGPT web chat connected to Bridge. It is your own local client.

## Prerequisites

1. **ARK Bridge** running (tray app or `ARK Bridge.exe`)
2. **Forge Core** running (Bridge starts it when configured)
3. **Python 3.11+**
4. **OpenAI API key**

## Setup

```powershell
cd ark-forge\console
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
copy console.env.example .env
```

Edit `.env` (gitignored):

```env
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5.5

ARK_BRIDGE_URL=http://127.0.0.1:9471
ARK_BRIDGE_TOKEN=paste-from-ark-bridge-pair
```

Get the bridge token: tray → **Pair**, or `ark-bridge pair`. Paste the bearer value only (not the `Authorization:` prefix).

## Verify Bridge (no OpenAI)

```powershell
python -m ark_console test-bridge
```

Should return live JSON from `core.git.status.read`.

## v0.1 proof

```powershell
python -m ark_console ask "What's my git status?"
```

Expected flow:

1. OpenAI receives your question
2. Model calls tool `core_git_status_read`
3. ARK Console forwards to `POST /bridge/observe`
4. Forge Core returns live git snapshot
5. Console returns tool output to OpenAI
6. Assistant answers from real data

## Interactive mode

```powershell
python -m ark_console
```

```
you> What's my git status?
assistant> ...
```

## v0.1 scope

| Included | Excluded |
|----------|----------|
| OpenAI Responses API | Assistants API |
| One tool: `core.git.status.read` | Other capabilities |
| Tool defs from `/bridge/state` | MCP |
| Local config (gitignored) | Streaming, memory, agents |
| `X-ARK-Client: ARK Console` | Production ARK SMS |

## Architecture notes

- **Observe** capabilities use `POST /bridge/observe`
- **Invoke** capabilities would use `POST /bridge/invoke` (none enabled in v0.1)
- Tool names use underscores (`core_git_status_read`) because OpenAI function names cannot contain dots
- Bridge records **Client: ARK Console** in tray/diagnostics when connected

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `401 unauthorized` | Re-copy token from Pair |
| `Forge Core offline` | Start ARK Bridge; check `forge_repo_path` in `%USERPROFILE%\.ark\bridge.json` |
| No tools from state | Ensure Forge Core is healthy; run `test-bridge` |
| Missing OPENAI_API_KEY | Fill `.env` |

Stop here for v0.1. Add tools only after this proof works on the floor.
