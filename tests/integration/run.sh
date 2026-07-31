#!/usr/bin/env sh
# End-to-end test against a real WordPress. See README.md, "Integration test".
#
#   sh tests/integration/run.sh
#
# Needs PHP with mysqli and gd, WP-CLI on the PATH, and a MySQL to talk to.
#
#   WMDS_IT_WORKDIR   Scratch directory            (/tmp/wmds-integration)
#   WMDS_IT_PORT      Port of the mock server      (8089)
#   WMDS_IT_WP        WordPress version            (latest)
#   WMDS_IT_DB_NAME   Database name                (wmds_integration)
#   WMDS_IT_DB_USER   Database user                (root)
#   WMDS_IT_DB_PASS   Database secret              (wordpress)
#   WMDS_IT_DB_HOST   Database host                (127.0.0.1)

set -eu

root=$(cd "$(dirname "$0")/../.." && pwd)
work=${WMDS_IT_WORKDIR:-/tmp/wmds-integration}
wp_path="$work/wordpress"
state="$work/state"
port=${WMDS_IT_PORT:-8089}
wp_version=${WMDS_IT_WP:-latest}

WMDS_MOCK_BASE="http://127.0.0.1:$port"
WMDS_MOCK_STATE="$state"
WMDS_MOCK_USER=${WMDS_MOCK_USER:-wmds-ci}
WMDS_MOCK_PASS=${WMDS_MOCK_PASS:-mock-secret-4711}
WMDS_MOCK_SELLER=${WMDS_MOCK_SELLER:-99999999}
export WMDS_MOCK_BASE WMDS_MOCK_STATE WMDS_MOCK_USER WMDS_MOCK_PASS WMDS_MOCK_SELLER

db_name=${WMDS_IT_DB_NAME:-wmds_integration}
db_user=${WMDS_IT_DB_USER:-root}
db_secret=${WMDS_IT_DB_PASS:-wordpress}
db_host=${WMDS_IT_DB_HOST:-127.0.0.1}

say() {
	echo
	echo "== $1 =="
}

fail() {
	echo "$1" >&2
	exit 1
}

allow_root=""
if [ "$(id -u)" = "0" ]; then
	allow_root="--allow-root"
fi

wpcli() {
	# shellcheck disable=SC2086
	wp --path="$wp_path" $allow_root "$@"
}

scenario() {
	printf '%s' "$1" > "$state/state.json"
}

phase() {
	WMDS_IT_PHASE="$1"
	export WMDS_IT_PHASE
	wpcli eval-file "$root/tests/integration/assert.php"
}

command -v php > /dev/null 2>&1 || fail "PHP is required."
command -v wp > /dev/null 2>&1 || fail "WP-CLI is required - see https://wp-cli.org."
php -r 'exit( extension_loaded( "mysqli" ) ? 0 : 1 );' || fail "PHP needs the mysqli extension."
php -r 'exit( extension_loaded( "gd" ) ? 0 : 1 );' || fail "PHP needs the gd extension to build attachment metadata."

say "Database"
php -r '
for ( $i = 0; $i < 60; $i++ ) {
	$link = mysqli_connect( $argv[1], $argv[2], $argv[3] );
	if ( $link ) {
		echo "reachable at " . $argv[1] . "\n";
		exit( 0 );
	}
	sleep( 2 );
}
fwrite( STDERR, "no database at " . $argv[1] . "\n" );
exit( 1 );
' "$db_host" "$db_user" "$db_secret" 2> /dev/null || fail "No database at $db_host."

say "Fresh workspace"
case "$work" in
	/*/?*) ;;
	*) fail "WMDS_IT_WORKDIR must be an absolute path below the root, got '$work'." ;;
esac
if [ "$work" = "${HOME:-}" ]; then
	fail "WMDS_IT_WORKDIR points at the home directory."
fi
case "$root/" in
	"$work"/*) fail "WMDS_IT_WORKDIR contains the checkout at $root." ;;
esac
rm -rf "$work"
mkdir -p "$wp_path" "$state"
echo "$work"

say "WordPress $wp_version"
wpcli core download --version="$wp_version" --force
wpcli config create \
	--dbname="$db_name" \
	--dbuser="$db_user" \
	--dbpass="$db_secret" \
	--dbhost="$db_host" \
	--skip-check --force --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
PHP
wpcli db reset --yes || wpcli db create
wpcli core install \
	--url="http://wmds.test" \
	--title="mobile.de sync integration" \
	--admin_user=admin \
	--admin_email=admin@example.invalid \
	--admin_password=integration-secret \
	--skip-email

say "Plugin and harness in place"
plugin_dir="$wp_path/wp-content/plugins/wp-mobile-de-sync"
mkdir -p "$plugin_dir"
for item in wp-mobile-de-sync.php uninstall.php readme.txt includes templates assets languages; do
	if [ -e "$root/$item" ]; then
		cp -R "$root/$item" "$plugin_dir/"
	fi
done
mkdir -p "$wp_path/wp-content/mu-plugins"
cp "$root/tests/integration/mu-plugin.php" "$wp_path/wp-content/mu-plugins/wmds-mock-transport.php"
wpcli plugin activate wp-mobile-de-sync

phase unconfigured

wpcli option update wmds_settings --format=json "$(
	printf '{"username":"%s","password":"%s","seller_id":"%s","language":"en","interval":"wmds_15min"}' \
		"$WMDS_MOCK_USER" "$WMDS_MOCK_PASS" "$WMDS_MOCK_SELLER"
)"

say "Mock mobile.de on port $port"
php -S "127.0.0.1:$port" "$root/tests/integration/mock-server.php" > "$work/mock.log" 2>&1 &
mock_pid=$!
# shellcheck disable=SC2064
trap "kill $mock_pid 2> /dev/null || true" EXIT HUP INT TERM

ready=0
attempt=0
while [ "$attempt" -lt 30 ]; do
	if curl -fsS --noproxy '*' "$WMDS_MOCK_BASE/__ping" > /dev/null 2>&1; then
		ready=1
		break
	fi
	attempt=$((attempt + 1))
	sleep 1
done
[ "$ready" -eq 1 ] || {
	cat "$work/mock.log"
	fail "The mock server did not come up on port $port."
}
echo "up"

say "Connection test"
scenario '{"ads":["424776053","427312402"],"page_size":1}'
output=$(wpcli wmds test)
echo "$output"
echo "$output" | grep -q 'Connection OK - 2 vehicles' \
	|| fail "The connection test did not report the inventory size."

say "Wrong credentials are rejected"
wpcli option patch update wmds_settings password not-the-secret
if output=$(wpcli wmds test 2>&1); then
	echo "$output"
	fail "The connection test passed although the secret was wrong."
fi
echo "$output"
echo "$output" | grep -q 'HTTP 401' || fail "Expected the 401 hint from the API."
wpcli option patch update wmds_settings password "$WMDS_MOCK_PASS"

say "Full sync"
: > "$state/requests.log"
wpcli wmds sync --full --all
grep -q 'page.number=2' "$state/requests.log" \
	|| fail "The importer never asked for a second page."
grep -q '/search-api/ad/424776053' "$state/requests.log" \
	|| fail "The importer never made the single-ad call."
phase full-sync

say "Incremental sync without changes"
: > "$state/requests.log"
wpcli wmds sync
grep -q 'modificationTime.min=' "$state/requests.log" \
	|| fail "The incremental run did not send a watermark."
phase unchanged

say "One vehicle changes"
scenario '{"ads":["424776053","427312402"],"modified":{"427312402":"2026-07-29T09:00:00+02:00"},"model":{"427312402":"V90 Facelift Edition"}}'
wpcli wmds sync
phase updated

say "One vehicle is reloaded on request"
scenario '{"ads":["424776053","427312402"],"modified":{"427312402":"2026-07-29T09:00:00+02:00"},"model":{"424776053":"Defender 90 Works V8","427312402":"V90 Facelift Edition"}}'
: > "$state/requests.log"
outcome=$(wpcli eval '$importer = new WMDS_Importer(); $result = $importer->refresh( "424776053" ); echo is_wp_error( $result ) ? "error: " . $result->get_error_message() : $result["outcome"];')
echo "$outcome"
[ "$outcome" = "updated" ] || fail "The reload did not report an update: $outcome"
grep -q '/search-api/ad/424776053' "$state/requests.log" \
	|| fail "The reload did not call the single-ad endpoint."
if grep -q '/search-api/search' "$state/requests.log"; then
	fail "The reload ran a search; it is meant to fetch one ad."
fi
phase reloaded

say "A vehicle disappears from the feed"
scenario '{"ads":["424776053"]}'
wpcli wmds sync --full --all
phase removed

say "The API fails"
scenario '{"ads":["424776053"],"search_status":500}'
if wpcli wmds sync --full > "$work/failed-sync.log" 2>&1; then
	cat "$work/failed-sync.log"
	fail "The sync reported success although the API answered 500."
fi
cat "$work/failed-sync.log"
phase api-error

say "Status"
wpcli wmds status

say "The German catalogue translates"
phase translation

say "Uninstall keeps the vehicles and removes the plugin's own data"
wpcli option update wmds_dashboard --format=json '{"count":5,"orderby":"date"}'
wpcli transient set wmds_makes VOLVO
wpcli transient set wmds_notices_probe held
wpcli user meta update admin wmds_dismissed stale
wpcli plugin uninstall wp-mobile-de-sync --deactivate --skip-delete
phase uninstalled

[ -f "$root/wp-mobile-de-sync.php" ] || fail "The uninstall reached the working tree."

echo
echo "All integration phases passed."
