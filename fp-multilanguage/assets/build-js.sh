#!/bin/bash
# Build script per bundle JavaScript (semplice concatenazione)
# @since 0.3.2
# 
# Nota: Per progetti più complessi, considera l'uso di webpack, rollup o esbuild

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
JS_DIR="$SCRIPT_DIR/js"
OUTPUT_FILE="$SCRIPT_DIR/admin-compiled.js"

echo "📦 Building JavaScript modules..."

cat > "$OUTPUT_FILE" << 'EOF'
/**
 * Admin scripts for FP Multilanguage - Compiled bundle
 * @since 0.3.2
 * 
 * Questo file è generato automaticamente.
 * Per modifiche, edita i file nella cartella js/ ed esegui build-js.sh
 */

(function() {
'use strict';

EOF

# Funzione per rimuovere import/export e convertire in IIFE
process_module() {
    local file=$1
    local module_name=$(basename "$file" .js)
    
    echo "" >> "$OUTPUT_FILE"
    echo "// Module: $module_name" >> "$OUTPUT_FILE"
    echo "const ${module_name//-/_} = (function() {" >> "$OUTPUT_FILE"
    
    # Rimuove import statements e converte export in return
    sed -E \
        -e '/^import .* from/d' \
        -e 's/^export const /const /g' \
        -e 's/^export function /function /g' \
        "$file" >> "$OUTPUT_FILE"
    
    echo "" >> "$OUTPUT_FILE"
    
    # Ritorna le funzioni esportate
    if grep -q "^const\|^function" "$file"; then
        echo "  return {" >> "$OUTPUT_FILE"
        grep -E "^(export )?const |^(export )?function " "$file" | \
            sed -E 's/^(export )?(const|function) ([a-zA-Z_][a-zA-Z0-9_]*).*/    \3,/' >> "$OUTPUT_FILE"
        echo "  };" >> "$OUTPUT_FILE"
    fi
    
    echo "})();" >> "$OUTPUT_FILE"
}

# Processa i moduli nell'ordine corretto
for file in \
    "$JS_DIR/utils.js" \
    "$JS_DIR/template-engine.js" \
    "$JS_DIR/toggle.js" \
    "$JS_DIR/api-client.js" \
    "$JS_DIR/diagnostics.js" \
    "$JS_DIR/action-buttons.js"
do
    if [ -f "$file" ]; then
        process_module "$file"
    fi
done

# Aggiunge l'inizializzazione
cat >> "$OUTPUT_FILE" << 'EOF'

// Inizializzazione
(function() {
    toggle.initToggles();
    
    const feedback = document.querySelector('#fpml-diagnostics-feedback');
    const providerResult = document.querySelector('[data-fpml-provider-result]');
    
    action_buttons.initActionButtons(feedback, providerResult);
})();

})();
EOF

echo "✅ JavaScript compiled to admin-compiled.js"