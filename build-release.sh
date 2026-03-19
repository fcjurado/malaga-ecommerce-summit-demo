#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="malaga-ecommerce-summit-demo"
MAIN_FILE="$SCRIPT_DIR/$PLUGIN_SLUG.php"
DIST_DIR="$SCRIPT_DIR/dist"

VERSION="$(sed -n 's/^ \* Version: //p' "$MAIN_FILE" | head -n 1)"

if [[ -z "$VERSION" ]]; then
    echo "Could not determine plugin version from $MAIN_FILE" >&2
    exit 1
fi

ZIP_NAME="$PLUGIN_SLUG-$VERSION.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"
STAGING_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$STAGING_DIR"
}

trap cleanup EXIT

mkdir -p "$DIST_DIR" "$STAGING_DIR/$PLUGIN_SLUG"

cp "$MAIN_FILE" "$STAGING_DIR/$PLUGIN_SLUG/"

for file in README.md CHANGELOG.md; do
    if [[ -f "$SCRIPT_DIR/$file" ]]; then
        cp "$SCRIPT_DIR/$file" "$STAGING_DIR/$PLUGIN_SLUG/"
    fi
done

rm -f "$ZIP_PATH"

(
    cd "$STAGING_DIR"
    zip -rq "$ZIP_PATH" "$PLUGIN_SLUG"
)

echo "Created $ZIP_PATH"
unzip -l "$ZIP_PATH"