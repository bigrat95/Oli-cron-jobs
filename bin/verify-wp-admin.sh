#!/usr/bin/env bash
# Verify wp-admin, plugins.php, and Tools > Cron return HTTP 200.
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8080}"
COOKIE_JAR="${COOKIE_JAR:-/tmp/oli-cj-wp-cookies.txt}"

fail() {
	echo "FAIL: $*" >&2
	exit 1
}

check_status() {
	local path="$1"
	local expected="${2:-200}"
	local status
	status="$(curl -sS -o /tmp/oli-cj-wp-body.html -w '%{http_code}' -b "$COOKIE_JAR" -c "$COOKIE_JAR" "${BASE_URL}${path}")"
	if [[ "$status" != "$expected" ]]; then
		fail "${path} returned ${status}, expected ${expected}"
	fi
	echo "OK ${status} ${path}"
}

rm -f "$COOKIE_JAR"

check_status "/wp-login.php" 200

USER="${WP_ADMIN_USER:-admin}"
PASS="${WP_ADMIN_PASSWORD:-password}"

curl -sS -o /dev/null -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
	-X POST \
	-d "log=${USER}&pwd=${PASS}&wp-submit=Log+In&testcookie=1" \
	"${BASE_URL}/wp-login.php"

check_status "/wp-admin/" 200
check_status "/wp-admin/plugins.php" 200
check_status "/wp-admin/tools.php?page=oli-cron-jobs" 200
