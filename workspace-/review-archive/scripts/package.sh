#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/plugins/realestate-platform"
VERSION=$(sed -n 's/^ \* Version: //p' "$SRC/realestate-platform.php" | head -1)
OUT="$ROOT/dist/realestate-platform-$VERSION.zip"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/realestate-platform"
cp -a "$SRC/." "$TMP/realestate-platform/"
rm -rf "$TMP/realestate-platform/tests" "$TMP/realestate-platform/vendor" "$TMP/realestate-platform/.git"
rm -f "$TMP/realestate-platform/.phpunit.result.cache" "$TMP/realestate-platform/phpunit.xml" "$TMP/realestate-platform/phpstan.neon" "$TMP/realestate-platform/phpcs.xml" "$TMP/realestate-platform/composer.json" "$TMP/realestate-platform/composer.lock"
find "$TMP/realestate-platform" -type f -exec touch -t 202608270000 {} +
mkdir -p "$ROOT/dist"
rm -f "$OUT"
(cd "$TMP" && find realestate-platform -type f -print | LC_ALL=C sort | zip -X -q "$OUT" -@)
echo "$OUT"
