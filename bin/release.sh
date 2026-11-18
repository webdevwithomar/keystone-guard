#!/usr/bin/env bash
# Usage: bin/release.sh 1.0.1
set -euo pipefail

VERSION="${1:?Give the version, for example 1.0.1}"
TAG="v$VERSION"
REPO="webdevwithomar/keystone-guard"

grep -q "^ \* Version: *$VERSION$" keystone-guard.php
grep -q "'KG_VERSION', '$VERSION'" keystone-guard.php
test -z "$(git status --porcelain)"

OUT="$(mktemp -d)"
trap 'rm -rf "$OUT"' EXIT

git tag "$TAG"
git archive --format=zip --prefix=keystone-guard/ -o "$OUT/keystone-guard.zip" "$TAG"
SHA="$(sha256sum "$OUT/keystone-guard.zip" | cut -d' ' -f1)"
printf '{"version":"%s","package":"https://github.com/%s/releases/download/%s/keystone-guard.zip","sha256":"%s"}\n' "$VERSION" "$REPO" "$TAG" "$SHA" > "$OUT/update.json"

git -c credential.helper= -c 'credential.helper=!gh auth git-credential' push origin HEAD "$TAG"
gh release create "$TAG" "$OUT/keystone-guard.zip" "$OUT/update.json" -R "$REPO" --title "$TAG" --generate-notes
