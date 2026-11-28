#!/usr/bin/env bash
# Build a public ARK Core release ZIP for traditional / shared hosting.
# End users of the ZIP must not need Docker, SSH, Composer, or Node.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMMIT="$(git rev-parse HEAD)"
SHORT="$(git rev-parse --short HEAD)"
VERSION="${ARK_RELEASE_VERSION:-dev-$SHORT}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_DIR="${ARK_RELEASE_OUT_DIR:-$ROOT/dist}"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/ark-core-release.XXXXXX")"
NAME="ark-core-${VERSION}-${SHORT}"
STAGE="$WORK/$NAME"

cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

mkdir -p "$STAGE" "$OUT_DIR"

echo "== copy application tree"
rsync -a \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='dist' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='.env' \
  --exclude='tests' \
  --exclude='review-artifacts' \
  "$ROOT/" "$STAGE/"

echo "== composer --no-dev"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --working-dir="$STAGE"

echo "== frontend build (build host only)"
if [[ ! -d "$ROOT/node_modules" ]]; then
  npm ci --prefix "$ROOT"
fi
npm run build --prefix "$ROOT"
mkdir -p "$STAGE/public/build"
rsync -a "$ROOT/public/build/" "$STAGE/public/build/"

echo "== release metadata"
mkdir -p "$STAGE/storage/app" "$STAGE/storage/framework/cache" "$STAGE/storage/framework/sessions" "$STAGE/storage/framework/views" "$STAGE/storage/logs" "$STAGE/bootstrap/cache"
cat > "$STAGE/RELEASE.json" <<EOF
{
  "name": "ark-core",
  "version": "$VERSION",
  "commit": "$COMMIT",
  "built_at": "$STAMP",
  "php": "^8.3",
  "license": "AGPL-3.0-only",
  "document_root": "public",
  "setup": "/setup"
}
EOF
printf '%s\n' "$COMMIT" > "$STAGE/.ark-source-commit"

echo "== zip"
ZIP="$OUT_DIR/${NAME}.zip"
rm -f "$ZIP"
(
  cd "$WORK"
  zip -qr "$ZIP" "$NAME"
)
SHA="$(shasum -a 256 "$ZIP" | awk '{print $1}')"
echo "$SHA  $(basename "$ZIP")" > "${ZIP}.sha256"

echo "artifact=$ZIP"
echo "sha256=$SHA"
echo "version=$VERSION"
echo "commit=$COMMIT"
