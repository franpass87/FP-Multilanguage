#!/bin/bash
# Build script per concatenare i moduli CSS
# @since 0.3.2

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CSS_DIR="$SCRIPT_DIR/css"
OUTPUT_FILE="$SCRIPT_DIR/admin-compiled.css"

echo "🎨 Building CSS modules..."

# Crea il file di output con header
cat > "$OUTPUT_FILE" << 'EOF'
/**
 * Admin styles for FP Multilanguage - Compiled bundle
 * @since 0.3.2
 * 
 * Questo file è generato automaticamente.
 * Per modifiche, edita i file nella cartella css/ ed esegui build-css.sh
 */

EOF

# Concatena tutti i moduli CSS nell'ordine corretto
for file in \
    "$CSS_DIR/_variables.css" \
    "$CSS_DIR/layout.css" \
    "$CSS_DIR/forms.css" \
    "$CSS_DIR/tables.css" \
    "$CSS_DIR/cards.css" \
    "$CSS_DIR/diagnostics.css"
do
    if [ -f "$file" ]; then
        echo "" >> "$OUTPUT_FILE"
        echo "/* $(basename "$file") */" >> "$OUTPUT_FILE"
        cat "$file" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
    fi
done

echo "✅ CSS compiled to admin-compiled.css"