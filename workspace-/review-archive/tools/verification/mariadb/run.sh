#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
source "$ROOT/tools/verification/common/safety.sh"
export REP_TEST_DB_PASSWORD="${REP_TEST_DB_PASSWORD:-rep-${RANDOM}-${RANDOM}}"
export REP_TEST_ROOT_PASSWORD="${REP_TEST_ROOT_PASSWORD:-root-${RANDOM}-${RANDOM}}"
HERE="$ROOT/tools/verification/mariadb"; OUTDIR="$ROOT/evidence/phase-1/database/mariadb"; OUT="$OUTDIR/lifecycle.json"
mkdir -p "$OUTDIR"
cleanup(){ [[ "${KEEP_ENV:-0}" == 1 ]] || docker compose -f "$HERE/docker-compose.yml" down -v --remove-orphans >/dev/null 2>&1 || true; }; trap cleanup EXIT
docker compose -f "$HERE/docker-compose.yml" up -d --wait
dc=(docker compose -f "$HERE/docker-compose.yml" run --rm cli)
"${dc[@]}" wp core install --url=http://wordpress.test --title='Phase 1 MariaDB' --admin_user=admin --admin_password='disposable-test-only' --admin_email=admin@example.test --skip-email
"${dc[@]}" wp plugin activate realestate-platform
"${dc[@]}" wp eval-file /verification/lifecycle.php > "$OUT"
grep -q '"status": "PASS"' "$OUT"
"${dc[@]}" wp option update rep_unrelated_sentinel keep-me >/dev/null
"${dc[@]}" wp plugin uninstall realestate-platform --skip-delete >/dev/null
"${dc[@]}" wp eval-file /verification/verify-preserved-uninstall.php > "$OUTDIR/normal-uninstall.json"
grep -q '"status": "PASS"' "$OUTDIR/normal-uninstall.json"
echo "MariaDB PASS: $OUT"
