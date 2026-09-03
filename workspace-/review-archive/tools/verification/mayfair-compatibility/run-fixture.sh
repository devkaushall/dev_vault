#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
source "$ROOT/tools/verification/common/safety.sh"
export REP_TEST_DB_PASSWORD="${REP_TEST_DB_PASSWORD:-rep-${RANDOM}-${RANDOM}}"
export REP_TEST_ROOT_PASSWORD="${REP_TEST_ROOT_PASSWORD:-root-${RANDOM}-${RANDOM}}"
ENGINE="${1:-mysql}"; COMPOSE="$ROOT/tools/verification/$ENGINE/docker-compose.yml"; OUT="$ROOT/evidence/phase-1/compatibility/fixture-${ENGINE}.json"
[[ "$ENGINE" == mysql || "$ENGINE" == mariadb ]] || exit 2; mkdir -p "$(dirname "$OUT")"
cleanup(){ [[ "${KEEP_ENV:-0}" == 1 ]] || docker compose -f "$COMPOSE" down -v --remove-orphans >/dev/null 2>&1 || true; }; trap cleanup EXIT
docker compose -f "$COMPOSE" up -d --wait; dc=(docker compose -f "$COMPOSE" run --rm cli)
"${dc[@]}" wp core install --url=http://wordpress.test --title=Compatibility --admin_user=admin --admin_password='disposable-test-only' --admin_email=admin@example.test --skip-email >/dev/null
"${dc[@]}" wp plugin activate realestate-platform >/dev/null
"${dc[@]}" wp eval-file /verification/mayfair-fixture.php > "$OUT"; grep -q '"status": "PASS"' "$OUT"
echo 'Fixture Compatibility PASS; Real Mayfair Compatibility remains NOT VERIFIED.'
