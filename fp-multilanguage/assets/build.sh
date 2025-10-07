#!/bin/bash
# Build script principale per CSS e JavaScript
# @since 0.3.2

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "🚀 Building FP Multilanguage assets..."
echo ""

# Build CSS
bash "$SCRIPT_DIR/build-css.sh"

echo ""

# Build JavaScript
bash "$SCRIPT_DIR/build-js.sh"

echo ""
echo "🎉 Build completato!"