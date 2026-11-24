#!/usr/bin/env sh
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

say "The pages render, with the default theme and without a single notice"
web_port=$((port + 1))
page_id=$(wpcli post create --post_type=page --post_status=publish --post_title="Stock" \
	--post_content='[vehicle-filter] [vehicles posts_per_page="1" pagination="yes" filters="yes"] <!-- wp:wmds/vehicles {"postsPerPage":2} /--> <!-- wp:wmds/vehicle-count /--> [vehicle-enquiry]' \
	--porcelain)
vehicle=$(wpcli post list --post_type=fahrzeuge --posts_per_page=1 --field=post_name)

wpcli server --host=127.0.0.1 --port="$web_port" > "$work/web.log" 2>&1 &
web_pid=$!
trap "kill $mock_pid $web_pid 2> /dev/null || true" EXIT HUP INT TERM

attempt=0
until curl -fsS --noproxy '*' -o /dev/null "http://127.0.0.1:$web_port/wp-login.php" 2> /dev/null; do
	attempt=$((attempt + 1))
	[ "$attempt" -lt 30 ] || fail "WordPress did not come up on port $web_port."
	sleep 1
done

: > "$wp_path/wp-content/debug.log"

fetch() {
	status=$(curl -sS --noproxy '*' -H 'Host: wmds.test' -o "$work/page.html" -w '%{http_code}' "http://127.0.0.1:$web_port$2")
	[ "$status" = "200" ] || fail "$1 answered with HTTP $status."
	grep -q "$3" "$work/page.html" || fail "$1 does not contain '$3'."
	if grep -qE 'There has been a critical error|<b>(Fatal error|Warning|Notice|Deprecated)</b>' "$work/page.html"; then
		fail "$1 shows a PHP error."
	fi
	echo "   ok    $1"
}

fetch "the archive" "/?post_type=fahrzeuge" 'class="wmds-card"'
fetch "the archive carries the theme's own header" "/?post_type=fahrzeuge" 'wp-site-blocks'
fetch "the archive, filtered" "/?post_type=fahrzeuge&wmds_make=Volvo&wmds_price_max=50000&wmds_sort=price-asc" 'V90'
fetch "a filter that matches nothing" "/?post_type=fahrzeuge&wmds_q=zzzzzz" 'wmds-empty'
fetch "the filter form keeps the query string of its action" "/?post_type=fahrzeuge" 'name="post_type" value="fahrzeuge"'
fetch "a vehicle" "/?fahrzeuge=$vehicle" 'wmds-single'
fetch "a vehicle carries its enquiry form" "/?fahrzeuge=$vehicle" 'wmds-enquiry__form'
fetch "the shortcodes and blocks on a page" "/?page_id=$page_id" 'wmds-facets'
fetch "the list on that page" "/?page_id=$page_id" 'wmds-grid--shortcode'
fetch "its second page" "/?page_id=$page_id&paged=2" 'class="wmds-card"'

default_theme=$(wpcli theme list --status=active --field=name)
if wpcli theme install https://downloads.wordpress.org/theme/twentytwentyone.latest-stable.zip --activate > /dev/null 2>&1; then
	fetch "the archive in a classic theme" "/?post_type=fahrzeuge" 'class="wmds-card"'
	fetch "a vehicle in a classic theme" "/?fahrzeuge=$vehicle" 'wmds-single'
	wpcli theme activate "$default_theme" > /dev/null
else
	echo "   skip  classic theme could not be installed"
fi

kill "$web_pid" 2> /dev/null || true

if grep -vE '\[wp-mobile-de-sync\] ' "$wp_path/wp-content/debug.log" | grep -E 'PHP (Fatal|Warning|Notice|Deprecated|Parse)' | grep -v 'wp_update_\|wp_version_check\|WordPress.org' > "$work/php-errors.log"; then
	cat "$work/php-errors.log"
	fail "Rendering the pages wrote PHP errors to the log."
fi
echo "   ok    nothing in the debug log"

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
