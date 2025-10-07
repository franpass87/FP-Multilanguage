#!/bin/bash
# Script di verifica integrità moduli
# @since 0.3.2

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CSS_DIR="$SCRIPT_DIR/css"
JS_DIR="$SCRIPT_DIR/js"

echo "🔍 Verifica integrità moduli FP Multilanguage..."
echo ""

# Verifica moduli CSS
echo "📋 Moduli CSS:"
CSS_MODULES=(
    "_variables.css"
    "layout.css"
    "forms.css"
    "tables.css"
    "cards.css"
    "diagnostics.css"
)

CSS_OK=0
for module in "${CSS_MODULES[@]}"; do
    if [ -f "$CSS_DIR/$module" ]; then
        lines=$(wc -l < "$CSS_DIR/$module")
        echo "  ✅ $module ($lines righe)"
        ((CSS_OK++))
    else
        echo "  ❌ $module MANCANTE!"
    fi
done

echo ""

# Verifica moduli JavaScript
echo "📋 Moduli JavaScript:"
JS_MODULES=(
    "utils.js"
    "template-engine.js"
    "toggle.js"
    "api-client.js"
    "diagnostics.js"
    "action-buttons.js"
)

JS_OK=0
for module in "${JS_MODULES[@]}"; do
    if [ -f "$JS_DIR/$module" ]; then
        lines=$(wc -l < "$JS_DIR/$module")
        echo "  ✅ $module ($lines righe)"
        ((JS_OK++))
    else
        echo "  ❌ $module MANCANTE!"
    fi
done

echo ""

# Verifica file compilati
echo "📦 File compilati:"
if [ -f "$SCRIPT_DIR/admin-compiled.css" ]; then
    lines=$(wc -l < "$SCRIPT_DIR/admin-compiled.css")
    echo "  ✅ admin-compiled.css ($lines righe)"
else
    echo "  ❌ admin-compiled.css MANCANTE! Esegui build-css.sh"
fi

if [ -f "$SCRIPT_DIR/admin-compiled.js" ]; then
    lines=$(wc -l < "$SCRIPT_DIR/admin-compiled.js")
    echo "  ✅ admin-compiled.js ($lines righe)"
else
    echo "  ❌ admin-compiled.js MANCANTE! Esegui build-js.sh"
fi

echo ""

# Verifica script di build
echo "🔨 Script di build:"
BUILD_SCRIPTS=(
    "build.sh"
    "build-css.sh"
    "build-js.sh"
)

BUILD_OK=0
for script in "${BUILD_SCRIPTS[@]}"; do
    if [ -f "$SCRIPT_DIR/$script" ] && [ -x "$SCRIPT_DIR/$script" ]; then
        echo "  ✅ $script (eseguibile)"
        ((BUILD_OK++))
    elif [ -f "$SCRIPT_DIR/$script" ]; then
        echo "  ⚠️  $script (non eseguibile - esegui: chmod +x $script)"
    else
        echo "  ❌ $script MANCANTE!"
    fi
done

echo ""

# Sommario
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Sommario:"
echo "   CSS:   $CSS_OK/${#CSS_MODULES[@]} moduli"
echo "   JS:    $JS_OK/${#JS_MODULES[@]} moduli"
echo "   Build: $BUILD_OK/${#BUILD_SCRIPTS[@]} script"

TOTAL_OK=$((CSS_OK + JS_OK + BUILD_OK))
TOTAL_EXPECTED=$((${#CSS_MODULES[@]} + ${#JS_MODULES[@]} + ${#BUILD_SCRIPTS[@]}))

if [ $TOTAL_OK -eq $TOTAL_EXPECTED ]; then
    echo ""
    echo "✅ Tutti i moduli sono presenti e corretti!"
    echo ""
    echo "💡 Suggerimenti:"
    echo "   - Modalità dev: define('FPML_DEV_MODE', true);"
    echo "   - Build: bash build.sh"
    echo "   - Verifica: bash verify.sh"
else
    echo ""
    echo "⚠️  Alcuni moduli mancano o hanno problemi."
    echo "   Totale: $TOTAL_OK/$TOTAL_EXPECTED componenti OK"
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"