#!/usr/bin/env bash
# Integration test: `wp cron event run` of a URL event must HTTP-request and log.
set -euo pipefail

WP="${WP_CLI:-wp}"
HOOK="oli_cj_cli_url_event"
PORT="${OLI_CJ_SINK_PORT:-8765}"
SINK_DIR="${OLI_CJ_SINK_DIR:-/tmp/oli-cj-http-sink}"
SINK_FILE="${SINK_DIR}/request.log"

mkdir -p "$SINK_DIR"
rm -f "$SINK_FILE"

cat > "${SINK_DIR}/index.php" <<'PHP'
<?php
file_put_contents(__DIR__ . '/request.log', date('c') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . "\n", FILE_APPEND);
header('Content-Type: text/plain');
echo 'ok';
PHP

php -S "127.0.0.1:${PORT}" -t "$SINK_DIR" >/tmp/oli-cj-http-sink-server.log 2>&1 &
SINK_PID=$!
cleanup() {
	kill "$SINK_PID" >/dev/null 2>&1 || true
}
trap cleanup EXIT
sleep 0.3

URL="http://127.0.0.1:${PORT}/oli-cj-cli-url-event"

$WP eval "Oli_CJ_Logger::create_table(); Oli_CJ_Cron_Manager::delete_all_hook_events( '${HOOK}' ); Oli_CJ_Cron_Manager::add_url_event( '${HOOK}', time() + HOUR_IN_SECONDS, '${URL}' );"

$WP cron event run "$HOOK"

if [[ ! -s "$SINK_FILE" ]]; then
	echo "FAIL: wp cron event run did not request ${URL}" >&2
	exit 1
fi

LOG_OK="$($WP eval "echo Oli_CJ_Logger::get_log_count( array( 'hook' => '${HOOK}', 'event_type' => 'url' ) );")"
if [[ "${LOG_OK}" -lt 1 ]]; then
	echo "FAIL: URL event did not write a log row" >&2
	exit 1
fi

$WP eval "Oli_CJ_Cron_Manager::delete_all_hook_events( '${HOOK}' );"

echo "OK wp cron event run requested ${URL} and wrote a log row"
