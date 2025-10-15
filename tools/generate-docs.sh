#!/bin/bash

###############################################################################
# FP Multilanguage - Documentation Generator
#
# Genera documentazione automatica da PHPDoc
#
# Uso: ./generate-docs.sh [--output=docs/api]
###############################################################################

set -e

# Colori
BLUE='\033[0;34m'
GREEN='\033[0;32m'
NC='\033[0m'

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${PLUGIN_DIR}/docs/api-generated"

# Parsing argomenti
for arg in "$@"; do
    case $arg in
        --output=*)
            OUTPUT_DIR="${arg#*=}"
            shift
            ;;
    esac
done

echo -e "${BLUE}════════════════════════════════════════${NC}"
echo -e "${BLUE}FP Multilanguage - Doc Generator${NC}"
echo -e "${BLUE}════════════════════════════════════════${NC}\n"

# Crea directory output
mkdir -p "$OUTPUT_DIR"

echo -e "${BLUE}[1/4]${NC} Scansione classi..."

# Genera indice classi
cat > "$OUTPUT_DIR/INDEX.md" <<EOF
# FP Multilanguage - API Reference

Generato automaticamente il $(date +"%Y-%m-%d %H:%M:%S")

## Indice Classi

EOF

# Trova tutte le classi
find "$PLUGIN_DIR/fp-multilanguage/includes" -name "*.php" | while read -r file; do
    # Estrai nome classe
    CLASS_NAME=$(grep -o "class [A-Za-z_][A-Za-z0-9_]*" "$file" | head -1 | awk '{print $2}')
    
    if [ -n "$CLASS_NAME" ]; then
        RELATIVE_PATH=${file#$PLUGIN_DIR/fp-multilanguage/}
        echo "- [$CLASS_NAME]($CLASS_NAME.md) - \`$RELATIVE_PATH\`" >> "$OUTPUT_DIR/INDEX.md"
    fi
done

echo -e "${GREEN}✓${NC} Indice creato"

echo -e "\n${BLUE}[2/4]${NC} Estrazione PHPDoc..."

# Funzione per estrarre documentazione
extract_class_doc() {
    local file=$1
    local output_file=$2
    local class_name=$3
    
    # Header
    cat > "$output_file" <<EOF
# $class_name

**File:** \`${file#$PLUGIN_DIR/fp-multilanguage/}\`

## Descrizione

EOF
    
    # Estrai descrizione classe
    grep -A 5 "class $class_name" "$file" | grep " \* " | sed 's/ \* //' >> "$output_file"
    
    # Metodi pubblici
    echo -e "\n## Metodi Pubblici\n" >> "$output_file"
    
    grep -B 10 "public function" "$file" | while IFS= read -r line; do
        if [[ $line =~ "public function" ]]; then
            METHOD_NAME=$(echo "$line" | sed 's/.*function //' | sed 's/(.*$//')
            echo "### \`$METHOD_NAME()\`" >> "$output_file"
            echo "" >> "$output_file"
        elif [[ $line =~ "@param" ]]; then
            echo "- $line" | sed 's/\* //' >> "$output_file"
        elif [[ $line =~ "@return" ]]; then
            echo "- $line" | sed 's/\* //' >> "$output_file"
            echo "" >> "$output_file"
        fi
    done
}

# Genera documentazione per ogni classe
CLASSES_DOCUMENTED=0

find "$PLUGIN_DIR/fp-multilanguage/includes" -name "*.php" | while read -r file; do
    CLASS_NAME=$(grep -o "class [A-Za-z_][A-Za-z0-9_]*" "$file" | head -1 | awk '{print $2}')
    
    if [ -n "$CLASS_NAME" ]; then
        OUTPUT_FILE="$OUTPUT_DIR/$CLASS_NAME.md"
        extract_class_doc "$file" "$OUTPUT_FILE" "$CLASS_NAME"
        ((CLASSES_DOCUMENTED++)) || true
    fi
done

echo -e "${GREEN}✓${NC} PHPDoc estratto"

echo -e "\n${BLUE}[3/4]${NC} Generazione statistiche..."

# Genera statistiche
cat > "$OUTPUT_DIR/STATISTICS.md" <<EOF
# Statistiche Codice

Generato: $(date +"%Y-%m-%d %H:%M:%S")

## Metriche Generali

EOF

# Conta file, classi, funzioni
TOTAL_FILES=$(find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" | wc -l)
TOTAL_LINES=$(find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" -exec wc -l {} + | tail -1 | awk '{print $1}')
TOTAL_CLASSES=$(grep -r "^class " "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | wc -l)
TOTAL_FUNCTIONS=$(grep -r "function " "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | wc -l)
TOTAL_PHPDOC=$(grep -r "@param\|@return\|@since" "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | wc -l)

cat >> "$OUTPUT_DIR/STATISTICS.md" <<EOF
- **File PHP Totali:** $TOTAL_FILES
- **Righe di Codice:** $TOTAL_LINES
- **Classi:** $TOTAL_CLASSES
- **Funzioni/Metodi:** $TOTAL_FUNCTIONS
- **Tag PHPDoc:** $TOTAL_PHPDOC

## Coverage PHPDoc

PHPDoc Tags per funzione: $(echo "scale=2; $TOTAL_PHPDOC / $TOTAL_FUNCTIONS" | bc)

## Top 5 File per Dimensione

EOF

find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" -exec wc -l {} + | sort -rn | head -6 | tail -5 | while read -r lines file; do
    echo "- \`$(basename "$file")\`: $lines righe" >> "$OUTPUT_DIR/STATISTICS.md"
done

echo -e "${GREEN}✓${NC} Statistiche generate"

echo -e "\n${BLUE}[4/4]${NC} Creazione README..."

cat > "$OUTPUT_DIR/README.md" <<EOF
# Documentazione API Generata

Questa documentazione è stata generata automaticamente il $(date +"%Y-%m-%d").

## Files

- [INDEX.md](INDEX.md) - Indice di tutte le classi
- [STATISTICS.md](STATISTICS.md) - Statistiche del codice

## Come Usare

1. Inizia da [INDEX.md](INDEX.md) per vedere tutte le classi disponibili
2. Clicca su una classe per vederne i dettagli
3. Consulta [STATISTICS.md](STATISTICS.md) per metriche generali

## Aggiornamento

Rigenera questa documentazione con:

\`\`\`bash
./tools/generate-docs.sh
\`\`\`

---

**Nota:** Questa è documentazione auto-generata. Per documentazione curata manualmente, vedi la directory \`docs/\`.
EOF

echo -e "${GREEN}✓${NC} README creato"

# Summary
echo -e "\n${BLUE}════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ Documentazione generata con successo${NC}"
echo -e "${BLUE}════════════════════════════════════════${NC}"
echo -e "\nOutput directory: ${OUTPUT_DIR}"
echo -e "Files generati:"
echo -e "  - INDEX.md (indice classi)"
echo -e "  - STATISTICS.md (statistiche)"
echo -e "  - README.md (guida)"
echo -e "  - [Classi individuali].md\n"

exit 0
