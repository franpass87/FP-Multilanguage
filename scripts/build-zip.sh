#!/usr/bin/env bash
set -euo pipefail

SLUG="fp-multilanguage"
PLUGIN_DIR="${SLUG}"
MAIN_FILE="${PLUGIN_DIR}/${SLUG}.php"
STAGE="build/${SLUG}"
DIST_DIR="dist"

if [[ ! -f "${MAIN_FILE}" ]]; then
  echo "Errore: file principale del plugin non trovato: ${MAIN_FILE}" >&2
  exit 1
fi

VERSION=$(grep -Ei '^\s*\*\s*Version:\s*' "${MAIN_FILE}" | sed -E 's/.*Version:\s*([0-9A-Za-z\.-]+).*/\1/' || true)
if [[ -z "${VERSION}" ]]; then
  echo "Errore: impossibile determinare la versione dal file ${MAIN_FILE}" >&2
  exit 1
fi

OUTPUT="${DIST_DIR}/${SLUG}-${VERSION}.zip"
ARTIFACT_NAME="$(basename "${OUTPUT}")"

EXCLUDES=(
  ".git"
  ".github"
  ".gitignore"
  ".gitattributes"
  "build"
  "dist"
  "node_modules"
  "tests"
  "test"
  ".vscode"
  ".idea"
  "docs"
  "*~"
  "*.bak"
  "*.tmp"
  "*.md"
  "phpstan.neon"
  "phpunit.xml.dist"
  "composer.json"
  "composer.lock"
)

printf 'Versione rilevata: %s\n' "${VERSION}"
printf 'Esclusioni principali:\n'
for pattern in "${EXCLUDES[@]}"; do
  printf '  - %s\n' "$pattern"
done

if command -v composer &> /dev/null; then
  echo "Installazione dipendenze Composer in produzione..."
  rm -rf "${PLUGIN_DIR}/vendor"
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  composer dump-autoload -o --classmap-authoritative
else
  echo "ATTENZIONE: Composer non trovato nel PATH."
  if [[ ! -d "${PLUGIN_DIR}/vendor" ]]; then
    echo "Errore: La directory vendor non esiste e Composer non è disponibile per crearla." >&2
    echo "Installa Composer e riprova, oppure esegui 'composer install' manualmente prima di eseguire questo script." >&2
    exit 1
  fi
  echo "La directory vendor esiste già, continuo con il build..."
fi

rm -rf "${DIST_DIR}" "build"
mkdir -p "${DIST_DIR}" "${STAGE}"

RSYNC_EXCLUDES=()
for pattern in "${EXCLUDES[@]}"; do
  RSYNC_EXCLUDES+=("--exclude=${pattern}")
done

rsync -a "${RSYNC_EXCLUDES[@]}" "${PLUGIN_DIR}/" "${STAGE}/"

if command -v php &> /dev/null; then
  if find "${STAGE}" -type f -name '*.php' -print -quit | grep -q '.'; then
    echo "Controllo sintassi file PHP..."
    find "${STAGE}" -type f -name '*.php' -print0 | xargs -0 -n1 -P4 php -l
  fi
else
  echo "ATTENZIONE: PHP non trovato nel PATH, salto il controllo sintassi."
fi

echo "Creazione archivio: ${OUTPUT}"
(
  cd build
  zip -r "../${OUTPUT}" "${SLUG}"
)

du -h "${OUTPUT}"
echo "ARTIFACT=${OUTPUT}"
echo "ARTIFACT_NAME=${ARTIFACT_NAME}"
if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
  {
    echo "ARTIFACT=${OUTPUT}"
    echo "ARTIFACT_NAME=${ARTIFACT_NAME}"
  } >> "${GITHUB_OUTPUT}"
fi
