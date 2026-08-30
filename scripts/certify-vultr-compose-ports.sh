#!/usr/bin/env bash
# Assert Vultr merged Compose publishes only Caddy 80/443.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export ARK_DOMAIN="${ARK_DOMAIN:-cert.example.test}"
export ARK_ADMIN_EMAIL="${ARK_ADMIN_EMAIL:-cert@example.test}"
json="$(docker compose -f docker-compose.yml -f docker-compose.vultr.yml config --format json)"
python3 - "$json" <<'PY'
import json, sys
cfg = json.loads(sys.argv[1])
expected = {
    "caddy": {"80", "443"},
    "app": set(),
    "mysql": set(),
    "redis": set(),
}
errors = []
for name, want in expected.items():
    svc = cfg["services"].get(name)
    if svc is None:
        errors.append(f"missing service {name}")
        continue
    pubs = set()
    for p in svc.get("ports") or []:
        if isinstance(p, dict) and p.get("published") is not None:
            pubs.add(str(p["published"]))
    if pubs != want:
        errors.append(f"{name}: published={sorted(pubs)} want={sorted(want)}")
if errors:
    print("FAIL:")
    print("\n".join(errors))
    sys.exit(1)
print("PASS: vultr compose publishes only caddy 80/443")
PY
