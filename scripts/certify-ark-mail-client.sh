#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "Public ARK Mail client certification"
php artisan test tests/Feature/Mail/ArkMailClientTest.php
echo "PUBLIC ARK MAIL CLIENT: PASS"
