#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
source "$ROOT/tools/verification/common/safety.sh"
export REP_TEST_DB_PASSWORD="${REP_TEST_DB_PASSWORD:-rep-${RANDOM}-${RANDOM}}"
export REP_TEST_ROOT_PASSWORD="${REP_TEST_ROOT_PASSWORD:-root-${RANDOM}-${RANDOM}}"
ENGINE="${1:-mysql}"; [[ "$ENGINE" == mysql || "$ENGINE" == mariadb ]] || exit 2
COMPOSE="$ROOT/tools/verification/$ENGINE/docker-compose.yml"; OUTDIR="$ROOT/evidence/phase-1/uninstall/$ENGINE"; mkdir -p "$OUTDIR"
cleanup(){ [[ "${KEEP_ENV:-0}" == 1 ]] || docker compose -f "$COMPOSE" down -v --remove-orphans >/dev/null 2>&1 || true; }; trap cleanup EXIT
docker compose -f "$COMPOSE" up -d --wait; dc=(docker compose -f "$COMPOSE" run --rm cli)
install(){ "${dc[@]}" wp core install --url=http://wordpress.test --title=Uninstall --admin_user=admin --admin_password='disposable-test-only' --admin_email=admin@example.test --skip-email >/dev/null; "${dc[@]}" wp plugin activate realestate-platform >/dev/null; }
reset(){ "${dc[@]}" wp db reset --yes >/dev/null; install; }
install
# Deactivation preservation.
"${dc[@]}" wp option update rep_unrelated_sentinel keep-me >/dev/null; "${dc[@]}" wp plugin deactivate realestate-platform >/dev/null
"${dc[@]}" wp eval 'global $wpdb; $t=$wpdb->prefix."rep_schema_migrations"; $ok=get_option("rep_unrelated_sentinel")==="keep-me" && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$t))===$t; echo wp_json_encode(["status"=>$ok?"PASS":"FAIL","case"=>"deactivate"]); if(!$ok)exit(1);' > "$OUTDIR/deactivate.json"
for case in preserve partial purge; do reset; TEST_CASE="$case" "${dc[@]}" wp eval-file /verification/uninstall-case.php > "$OUTDIR/$case.json"; done
reset; "${dc[@]}" wp core multisite-convert --title='Disposable Network' >/dev/null; TEST_CASE=multisite "${dc[@]}" wp eval-file /verification/uninstall-case.php > "$OUTDIR/multisite.json"
python3 - "$OUTDIR" <<'PY'
import json,glob,sys
files=glob.glob(sys.argv[1]+'/*.json'); data=[json.load(open(f)) for f in files]
out={'status':'PASS' if len(data)==5 and all(x['status']=='PASS' for x in data) else 'FAIL','cases':data}
open(sys.argv[1]+'/result.json','w').write(json.dumps(out,indent=2)+'\n')
raise SystemExit(0 if out['status']=='PASS' else 1)
PY
cp "$OUTDIR/result.json" "$ROOT/tools/verification/uninstall/result.json"
