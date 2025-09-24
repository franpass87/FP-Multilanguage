#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="fp-multilanguage"
OUTPUT_DIR="$ROOT_DIR/build"
ZIP_FILE="$OUTPUT_DIR/$PLUGIN_SLUG.zip"

if [ -f "$ROOT_DIR/composer.json" ] && command -v composer >/dev/null 2>&1; then
    (cd "$ROOT_DIR" && composer install --no-dev --optimize-autoloader)
fi

rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR/$PLUGIN_SLUG"

rsync -av --exclude-from=- "$ROOT_DIR/$PLUGIN_SLUG/" "$OUTPUT_DIR/$PLUGIN_SLUG/" <<'RSYNC'
/vendor
/node_modules
/tests
/composer.json
/composer.lock
/phpunit.xml.dist
/phpstan.neon
/phpstan.neon.dist
/phpcs.xml
/phpcs.xml.dist
/package.json
/package-lock.json
/yarn.lock
/bin
/docs
/README.md
/QA_REPORT.md
/vite.config.js
RSYNC

if [ -d "$ROOT_DIR/vendor" ]; then
    rsync -av "$ROOT_DIR/vendor/" "$OUTPUT_DIR/$PLUGIN_SLUG/vendor/"
fi

(cd "$OUTPUT_DIR" && zip -r "$ZIP_FILE" "$PLUGIN_SLUG")

echo "Package created: $ZIP_FILE"
