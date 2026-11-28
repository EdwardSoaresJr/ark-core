#!/usr/bin/env bash
# Build a public ARK Core release ZIP from a clean committed source tree.
# Never packages .env, storage contents, keys, uploads, backups, or node_modules.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "ERROR: release ZIP requires a git work tree for provenance." >&2
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "ERROR: working tree is dirty. Commit or stash before packaging." >&2
  git status --porcelain >&2
  exit 1
fi

COMMIT="$(git rev-parse HEAD)"
SHORT="$(git rev-parse --short HEAD)"
TREE="$(git rev-parse HEAD^{tree})"
VERSION="${ARK_RELEASE_VERSION:-dev-$SHORT}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_DIR="${ARK_RELEASE_OUT_DIR:-$ROOT/dist}"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/ark-core-release.XXXXXX")"
NAME="ark-core-${VERSION}-${SHORT}"
STAGE="$WORK/$NAME"
BUILD_HOST="$WORK/build-host"

cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT

mkdir -p "$STAGE" "$OUT_DIR" "$BUILD_HOST"

echo "== clean source export (committed tree only)"
git archive --format=tar HEAD | tar -x -C "$STAGE"

# Explicit denylist — refuse if present after archive (should never ship).
DENY_PATTERNS=(
  '.env'
  '.env.*'
  '*.pem'
  '*.key'
  'id_rsa*'
  'storage/app/private/**'
  'storage/app/public/**'
  'storage/logs/**'
  'node_modules/**'
  'vendor/**'
  'dist/**'
  'review-artifacts/**'
  'database/*.sqlite'
  '*.sql'
  '*.sql.gz'
  '*.dump'
)

echo "== prune non-distribution paths"
rm -rf "$STAGE/review-artifacts" "$STAGE/tests" "$STAGE/.github" "$STAGE/.cursor" \
  "$STAGE/node_modules" "$STAGE/vendor" "$STAGE/dist" "$STAGE/storage/logs" \
  "$STAGE/storage/app/private" "$STAGE/storage/app/public" 2>/dev/null || true
rm -f "$STAGE/database/"*.sqlite "$STAGE/.env" 2>/dev/null || true
# Keep .env.example. Fail closed on real secrets / dumps.
while IFS= read -r -d '' path; do
  rel="${path#"$STAGE"/}"
  base="$(basename "$rel")"
  case "$base" in
    .env|*.pem|id_rsa|id_rsa.pub|id_ed25519|id_ed25519.pub)
      echo "ERROR: forbidden sensitive file in archive stage: $rel" >&2
      exit 1
      ;;
  esac
  case "$rel" in
    *.sql|*.sql.gz|*.dump|*.sqlite)
      echo "ERROR: forbidden data artifact in archive stage: $rel" >&2
      exit 1
      ;;
  esac
done < <(find "$STAGE" -type f -print0)
test -f "$STAGE/.env.example"

echo "== composer --no-dev (locked)"
test -f "$STAGE/.env.example"
cp composer.lock "$STAGE/composer.lock"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts --working-dir="$STAGE"
# Do not generate .env or APP_KEY here — browser /setup owns secrets on the target host.
mkdir -p "$STAGE/storage/framework/"{cache,sessions,views} \
  "$STAGE/storage/logs" "$STAGE/bootstrap/cache" "$STAGE/storage/app/mpdf-temp"
# Ensure package discovery metadata exists for offline hosts.
composer dump-autoload --optimize --working-dir="$STAGE" --no-interaction
test ! -e "$STAGE/.env"

echo "== frontend build on throwaway host copy"
git archive --format=tar HEAD | tar -x -C "$BUILD_HOST"
cp composer.lock "$BUILD_HOST/composer.lock"
(
  cd "$BUILD_HOST"
  composer install --no-interaction --prefer-dist --no-scripts >/dev/null
  npm ci
  npm run build
)
mkdir -p "$STAGE/public/build"
rsync -a "$BUILD_HOST/public/build/" "$STAGE/public/build/"
test -f "$STAGE/public/build/manifest.json"

echo "== secret / sensitive scan on staged tree"
if command -v gitleaks >/dev/null 2>&1; then
  gitleaks detect --source "$STAGE" --no-git --verbose || {
    echo "ERROR: gitleaks reported findings in staged release." >&2
    exit 1
  }
else
  # Lightweight fallback when gitleaks is not on the build agent.
  if grep -RInE 'BEGIN (RSA |OPENSSH |)PRIVATE KEY|AWS_SECRET_ACCESS_KEY|AKIA[0-9A-Z]{16}' "$STAGE" \
      --exclude-dir=vendor --exclude-dir=public/build --exclude='*.md' >/tmp/ark-release-secret-hits.txt 2>/dev/null; then
    echo "ERROR: sensitive pattern found:" >&2
    cat /tmp/ark-release-secret-hits.txt >&2
    exit 1
  fi
fi

# Ensure no .env slipped in
if [[ -e "$STAGE/.env" ]]; then
  echo "ERROR: .env must not be packaged." >&2
  exit 1
fi

echo "== license + manifest metadata"
mkdir -p "$STAGE/storage/app" "$STAGE/storage/framework/cache" "$STAGE/storage/framework/sessions" "$STAGE/storage/framework/views" "$STAGE/storage/logs" "$STAGE/bootstrap/cache"
printf '%s\n' "$COMMIT" > "$STAGE/.ark-source-commit"
composer licenses --no-dev --working-dir="$STAGE" --format=json > "$STAGE/COMPOSER_LICENSES.json" || true

(
  cd "$STAGE"
  find . -type f ! -path './COMPOSER_LICENSES.json' ! -path './RELEASE.json' ! -path './MANIFEST.sha256' \
    | LC_ALL=C sort \
    | while read -r f; do
        # portable sha256
        if command -v shasum >/dev/null 2>&1; then
          shasum -a 256 "$f"
        else
          sha256sum "$f"
        fi
      done
) > "$STAGE/MANIFEST.sha256"

cat > "$STAGE/RELEASE.json" <<EOF
{
  "name": "ark-core",
  "version": "$VERSION",
  "commit": "$COMMIT",
  "tree": "$TREE",
  "built_at": "$STAMP",
  "php": "^8.3",
  "license": "AGPL-3.0-only",
  "document_root": "public",
  "setup": "/setup",
  "composer_lock_hash": "$(php -r 'echo hash_file("sha256", $argv[1]);' "$STAGE/composer.lock")"
}
EOF

# Validate RELEASE.json
php -r '$j=json_decode(file_get_contents($argv[1]), true); foreach(["name","version","commit","tree","built_at","document_root","setup"] as $k){ if(empty($j[$k])){fwrite(STDERR,"missing $k\n"); exit(1);} }' "$STAGE/RELEASE.json"

echo "== zip"
ZIP="$OUT_DIR/${NAME}.zip"
rm -f "$ZIP" "${ZIP}.sha256"
(
  cd "$WORK"
  zip -qr "$ZIP" "$NAME"
)
if command -v shasum >/dev/null 2>&1; then
  SHA="$(shasum -a 256 "$ZIP" | awk '{print $1}')"
else
  SHA="$(sha256sum "$ZIP" | awk '{print $1}')"
fi
echo "$SHA  $(basename "$ZIP")" > "${ZIP}.sha256"

echo "== extract self-test"
EXTRACT="$WORK/extract-test"
mkdir -p "$EXTRACT"
unzip -q "$ZIP" -d "$EXTRACT"
test -f "$EXTRACT/$NAME/public/index.php"
test -f "$EXTRACT/$NAME/public/build/manifest.json"
test -d "$EXTRACT/$NAME/vendor/mpdf/mpdf"
test ! -e "$EXTRACT/$NAME/.env"
test -f "$EXTRACT/$NAME/RELEASE.json"
test -f "$EXTRACT/$NAME/MANIFEST.sha256"
php -r 'require $argv[1]."/vendor/autoload.php"; echo class_exists("Mpdf\\Mpdf")?"mpdf_ok\n":"mpdf_missing\n";' "$EXTRACT/$NAME"

echo "artifact=$ZIP"
echo "sha256=$SHA"
echo "version=$VERSION"
echo "commit=$COMMIT"
echo "tree=$TREE"
