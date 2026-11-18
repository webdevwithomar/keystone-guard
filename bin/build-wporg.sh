#!/usr/bin/env bash
# Usage: bin/build-wporg.sh
# Builds dist/keystone-guard-wporg.zip from the last commit for WordPress.org.
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"
test -z "$(git status --porcelain)"

OUT="$(mktemp -d)"
trap 'rm -rf "$OUT"' EXIT
git archive --format=tar --prefix=keystone-guard/ HEAD | tar -xf - -C "$OUT"
PLUGIN="$OUT/keystone-guard"

VERSION="$(sed -n 's/^ \* Version: *//p' "$PLUGIN/keystone-guard.php")"
grep -q "^Stable tag: $VERSION$" wporg/readme.txt

rm "$PLUGIN/includes/updater.php" "$PLUGIN/README.md"
cp wporg/readme.txt "$PLUGIN/readme.txt"

sed -i -E \
	-e '/^ \* Update URI:/d' \
	-e "/define\( 'KG_(REPO|MANAGED)'/d" \
	-e "/includes\/updater\.php/d" \
	"$PLUGIN/keystone-guard.php"
sed -i '/kg_update_manifest/d' "$PLUGIN/uninstall.php"
sed -i 's/(?:CUSTOM WORDPRESS|BRWEST)/CUSTOM WORDPRESS/g' "$PLUGIN/includes/htaccess.php"

# The directory expects a distinctive prefix of at least four characters.
find "$PLUGIN" -name '*.php' -exec sed -i -E 's/\bkg_/keystone_guard_/g; s/\bKG_/KEYSTONE_GUARD_/g' {} +

if grep -rnEi 'github|BRWEST|\bkg_|KEYSTONE_GUARD_(REPO|MANAGED)\b.*define' "$PLUGIN"; then
	echo "Build stopped: private or updater text is still present." >&2
	exit 1
fi

mkdir -p dist
rm -f dist/keystone-guard-wporg.zip
TARGET="$(pwd -W 2>/dev/null || pwd)/dist/keystone-guard-wporg.zip"
if command -v zip >/dev/null; then
	(cd "$OUT" && zip -qr "$TARGET" keystone-guard)
else
	(cd "$OUT" && /c/Windows/System32/tar.exe -a -c -f "$TARGET" keystone-guard)
fi
echo "Built dist/keystone-guard-wporg.zip ($VERSION)"
