#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Trova file principale del plugin: cerca il primo PHP con header Plugin Name
MAIN_FILE="$(grep -Rl --include="*.php" -m1 "^\\s*\\*\\s*Plugin Name:" "$ROOT_DIR" || true)"
if [[ -z "$MAIN_FILE" ]]; then
  echo "Errore: file principale del plugin non trovato." >&2
  exit 1
fi

PLUGIN_DIR="$(dirname "$MAIN_FILE")"
PLUGIN_BASENAME="$(basename "$PLUGIN_DIR")"

# Estrai Version dall'header
VERSION="$(grep -E "^[[:space:]]*\\*+[[:space:]]*Version:" "$MAIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
if [[ -z "$VERSION" ]]; then
  VERSION="0.0.0"
fi

# Slug: nome cartella, lowercase
SLUG="$(echo "$PLUGIN_BASENAME" | tr '[:upper:]' '[:lower:]')"

# Dist folder
DIST_DIR="$ROOT_DIR/dist"
mkdir -p "$DIST_DIR"

ZIP_NAME="${SLUG}-v${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

# Costruisci lista esclusioni
EXCLUDES=(
  "--exclude=.git" "--exclude=.git/*"
  "--exclude=.github" "--exclude=.github/*"
  "--exclude=node_modules" "--exclude=node_modules/*"
  "--exclude=tests" "--exclude=tests/*"
  "--exclude=docs" "--exclude=docs/*"
  "--exclude=dist" "--exclude=dist/*"
  "--exclude=.vscode" "--exclude=.idea"
  "--exclude=coverage" "--exclude=coverage/*"
  "--exclude=vendor/bin" "--exclude=vendor/bin/*"
)

cd "$(dirname "$PLUGIN_DIR")"

rm -f "$ZIP_PATH"
zip -r "$ZIP_PATH" "$PLUGIN_BASENAME" "${EXCLUDES[@]}"

echo "OK: creato $ZIP_PATH"
