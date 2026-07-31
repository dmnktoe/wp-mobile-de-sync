#!/usr/bin/env sh
# Run every test. Exits 1 as soon as one file fails.
# Deliberately without PHPUnit or Composer - runs on any PHP 7, no setup.
#
#   sh tests/run.sh

set -e
cd "$(dirname "$0")/.."

status=0

echo "== php -l across all PHP files =="
for file in wp-mobile-de-sync.php includes/*.php templates/*.php tests/*.php tests/integration/*.php bin/*.php; do
	php -l "$file" > /dev/null || status=1
done
[ "$status" -eq 0 ] && echo "   no syntax errors"

echo
echo "== source language =="
sh tests/no-german.sh || status=1

for test in tests/test-*.php; do
	echo
	echo "== $test =="
	php "$test" || status=1
done

echo
if [ "$status" -eq 0 ]; then
	echo "All tests passed."
else
	echo "At least one test failed."
fi
exit "$status"
