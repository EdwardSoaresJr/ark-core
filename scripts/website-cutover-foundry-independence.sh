#!/usr/bin/env bash
# Procedure only. This script does not stop Foundry.
set -euo pipefail

cat <<'EOF'
Do not run this until website-cutover-acceptance.sh has passed on both hosts.

1. Stop only the Foundry container on 149.28.249.13. Do not stop Core.
2. Run scripts/website-cutover-acceptance.sh again.
3. Confirm home, /book, a lead request, same-origin assets, sitemap, robots, and /llms.txt still return from Core.
4. Start Foundry again only if rollback should keep it available and unrouted.
   Leave its catch-all router disabled or at a priority that no longer receives public website paths.

This script does not SSH, stop a container, or change routing.
EOF
