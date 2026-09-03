#!/usr/bin/env bash
# Safety guard for destructive disposable verification.
set -euo pipefail
if [[ "${REALESTATE_VERIFICATION_DISPOSABLE:-}" != "YES" ]]; then
  echo "Refusing destructive verification. Set REALESTATE_VERIFICATION_DISPOSABLE=YES after confirming this is a disposable environment." >&2
  exit 64
fi
for variable in DATABASE_URL DB_HOST MYSQL_HOST MARIADB_HOST WORDPRESS_DB_HOST; do
  if [[ -n "${!variable:-}" ]]; then
    echo "Refusing externally supplied database target via $variable. This harness may use only its internal Compose database service." >&2
    exit 65
  fi
done
