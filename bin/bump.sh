#!/usr/bin/env sh
# Sets the version in the plugin header, in WMDS_VERSION and in readme.txt.
#
#   sh bin/bump.sh 2.0.2

set -eu
cd "$(dirname "$0")/.."

version=${1:-}

if [ -z "$version" ]; then
	echo "Usage: sh bin/bump.sh <version>" >&2
	exit 1
fi

case "$version" in
	[0-9]*.[0-9]*.[0-9]*) ;;
	*)
		echo "Not a version number: $version" >&2
		exit 1
		;;
esac

sed -i.bak -E "s/^([ \t*]*Version:[[:space:]]*)[^[:space:]]+/\1$version/" wp-mobile-de-sync.php
sed -i.bak -E "s/(define\( 'WMDS_VERSION', ')[^']*(' \);)/\1$version\2/" wp-mobile-de-sync.php
sed -i.bak -E "s/^(Stable tag:[[:space:]]*).*/\1$version/" readme.txt

rm -f wp-mobile-de-sync.php.bak readme.txt.bak

grep -m1 -E '^[ \t*]*Version:' wp-mobile-de-sync.php
grep -m1 "WMDS_VERSION" wp-mobile-de-sync.php
grep -m1 '^Stable tag:' readme.txt

echo
echo "Now write the entry for $version in CHANGELOG.md and readme.txt,"
echo "then merge to main. The release workflow tags and publishes it."
