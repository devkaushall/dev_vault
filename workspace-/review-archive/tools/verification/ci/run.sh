#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"; OUT="$ROOT/evidence/phase-1/ci/result.json"; mkdir -p "$(dirname "$OUT")"
if ! command -v gh >/dev/null || ! gh auth status >/dev/null 2>&1 || ! git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  printf '%s\n' '{"status":"NOT_VERIFIED","reason":"Authenticated GitHub CLI and repository context are required."}' > "$OUT"; cat "$OUT"; exit 3
fi
cd "$ROOT"; sha=$(git rev-parse HEAD); gh workflow run phase-1.yml --ref "$(git branch --show-current)"
sleep 5; run_id=$(gh run list --workflow phase-1.yml --commit "$sha" --limit 1 --json databaseId --jq '.[0].databaseId')
[[ -n "$run_id" ]] || { echo 'No workflow run found' >&2; exit 1; }; gh run watch "$run_id" --exit-status
GH_RUN_ID="$run_id" SHA="$sha" gh run view "$run_id" --json url,conclusion,jobs > "$OUT"
cat "$OUT"
