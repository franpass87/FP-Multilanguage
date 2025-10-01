#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
SLUG="fp-multilanguage"
PLUGIN_DIR="$ROOT_DIR/$SLUG"
BUILD_ROOT="$ROOT_DIR/build"
STAGING_DIR="$BUILD_ROOT/$SLUG"
ZIP_NAME=""
BUMP_ARG=""
SET_VERSION=""

while [ $# -gt 0 ]; do
    case "$1" in
        --set-version=*)
            SET_VERSION=${1#*=}
            ;;
        --set-version)
            shift
            if [ $# -eq 0 ]; then
                echo "Missing value for --set-version" >&2
                exit 1
            fi
            SET_VERSION=$1
            ;;
        --bump=*)
            BUMP_ARG=${1#*=}
            ;;
        --bump)
            BUMP_ARG="patch"
            ;;
        --zip-name=*)
            ZIP_NAME=${1#*=}
            ;;
        --zip-name)
            shift
            if [ $# -eq 0 ]; then
                echo "Missing value for --zip-name" >&2
                exit 1
            fi
            ZIP_NAME=$1
            ;;
        --help|-h)
            echo "Usage: bash build.sh [--set-version=X.Y.Z | --bump=patch|minor|major] [--zip-name=name]" >&2
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            exit 1
            ;;
    esac
    shift
done

if [ ! -d "$PLUGIN_DIR" ]; then
    echo "Plugin directory not found at $PLUGIN_DIR" >&2
    exit 1
fi

if [ -n "$SET_VERSION" ] && [ -n "$BUMP_ARG" ]; then
    echo "Cannot use --set-version together with --bump" >&2
    exit 1
fi

if [ -n "$SET_VERSION" ]; then
    php "$ROOT_DIR/tools/bump-version.php" --set="$SET_VERSION"
elif [ -n "$BUMP_ARG" ]; then
    case "$BUMP_ARG" in
        patch|minor|major)
            php "$ROOT_DIR/tools/bump-version.php" --bump="$BUMP_ARG"
            ;;
        *)
            echo "Invalid value for --bump: $BUMP_ARG" >&2
            exit 1
            ;;
    esac
fi

COMPOSER_CMD="composer"

rm -rf "$PLUGIN_DIR/vendor"

$COMPOSER_CMD install --no-dev --prefer-dist --no-interaction --optimize-autoloader
$COMPOSER_CMD dump-autoload -o --classmap-authoritative

mkdir -p "$BUILD_ROOT"
rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"

RSYNC_EXCLUDES="
    --exclude=.git
    --exclude=.github
    --exclude=tests
    --exclude=docs
    --exclude=node_modules
    --exclude=*.md
    --exclude=.idea
    --exclude=.vscode
    --exclude=build
    --exclude=.gitattributes
    --exclude=.gitignore
"

# shellcheck disable=SC2086
rsync -a --delete $RSYNC_EXCLUDES "$PLUGIN_DIR"/ "$STAGING_DIR"/

TIMESTAMP=$(date +%Y%m%d%H%M)
if [ -z "$ZIP_NAME" ]; then
    ZIP_BASENAME="${SLUG}-${TIMESTAMP}.zip"
else
    ZIP_BASENAME="$ZIP_NAME"
fi

ZIP_PATH="$BUILD_ROOT/$ZIP_BASENAME"
rm -f "$ZIP_PATH"

(
    cd "$BUILD_ROOT"
    zip -rq "$ZIP_BASENAME" "$SLUG"
)

VERSION=$(php -r '$file = file_get_contents("'"$PLUGIN_DIR/fp-multilanguage.php"'"); if ($file === false) { exit(1); } if (preg_match("/^\\s*\\*\\s*Version:\\s*(.+)$/mi", $file, $m)) { echo trim($m[1]); } else { exit(1); }') || {
    echo "Failed to read plugin version" >&2
    exit 1
}

echo "Version: $VERSION"
echo "Zip: $ZIP_PATH"
