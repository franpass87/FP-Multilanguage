#!/bin/bash

###############################################################################
# FP Multilanguage - Plugin Health Check Script
# 
# Esegue controlli di salute sul plugin e identifica potenziali problemi
#
# Uso: ./check-plugin-health.sh
###############################################################################

set -e

# Colori output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ERRORS=0
WARNINGS=0

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}FP Multilanguage - Health Check${NC}"
echo -e "${BLUE}========================================${NC}\n"

# 1. Verifica sintassi PHP
echo -e "${BLUE}[1/8]${NC} Controllo sintassi PHP..."
if find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" -exec php -l {} \; 2>&1 | grep -i "error" > /dev/null; then
    echo -e "${RED}✗ Errori di sintassi trovati${NC}"
    ((ERRORS++))
else
    echo -e "${GREEN}✓ Sintassi PHP corretta${NC}"
fi

# 2. Verifica file richiesti
echo -e "\n${BLUE}[2/8]${NC} Controllo file essenziali..."
REQUIRED_FILES=(
    "fp-multilanguage/fp-multilanguage.php"
    "fp-multilanguage/includes/core/class-plugin.php"
    "fp-multilanguage/includes/core/class-container.php"
    "fp-multilanguage/includes/class-queue.php"
    "fp-multilanguage/includes/class-settings.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$PLUGIN_DIR/$file" ]; then
        echo -e "${RED}✗ File mancante: $file${NC}"
        ((ERRORS++))
    fi
done

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ Tutti i file essenziali presenti${NC}"
fi

# 3. Verifica sicurezza
echo -e "\n${BLUE}[3/8]${NC} Controllo sanitizzazione input..."
UNSANITIZED=$(grep -r "\$_GET\[" "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | grep -v "sanitize" | grep -v "phpcs:ignore" | wc -l)
if [ "$UNSANITIZED" -gt 0 ]; then
    echo -e "${YELLOW}⚠ Trovati $UNSANITIZED possibili input non sanitizzati${NC}"
    ((WARNINGS++))
else
    echo -e "${GREEN}✓ Input sanitizzati correttamente${NC}"
fi

# 4. Verifica escaping output
echo -e "\n${BLUE}[4/8]${NC} Controllo escaping output..."
UNESCAPED=$(grep -r "echo \$" "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | grep -v "esc_" | grep -v "phpcs:ignore" | wc -l)
if [ "$UNESCAPED" -gt 0 ]; then
    echo -e "${YELLOW}⚠ Trovati $UNESCAPED possibili output non escaped${NC}"
    ((WARNINGS++))
else
    echo -e "${GREEN}✓ Output escaped correttamente${NC}"
fi

# 5. Verifica nonce
echo -e "\n${BLUE}[5/8]${NC} Controllo verifica nonce..."
POST_HANDLERS=$(grep -r "admin_post_" "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | wc -l)
NONCE_CHECKS=$(grep -r "check_admin_referer\|wp_verify_nonce" "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | wc -l)

if [ "$NONCE_CHECKS" -lt "$POST_HANDLERS" ]; then
    echo -e "${YELLOW}⚠ Alcuni handler potrebbero mancare verifica nonce${NC}"
    ((WARNINGS++))
else
    echo -e "${GREEN}✓ Verifica nonce implementata${NC}"
fi

# 6. Verifica prepared statements
echo -e "\n${BLUE}[6/8]${NC} Controllo SQL injection prevention..."
DIRECT_QUERY=$(grep -r "\$wpdb->query(" "$PLUGIN_DIR/fp-multilanguage" --include="*.php" | grep -v "prepare" | grep -v "DROP TABLE" | wc -l)
if [ "$DIRECT_QUERY" -gt 0 ]; then
    echo -e "${YELLOW}⚠ Trovate $DIRECT_QUERY query senza prepare (verificare manualmente)${NC}"
    ((WARNINGS++))
else
    echo -e "${GREEN}✓ SQL queries correttamente prepared${NC}"
fi

# 7. Verifica dimensioni file
echo -e "\n${BLUE}[7/8]${NC} Controllo dimensioni file..."
LARGE_FILES=$(find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" -size +100k)
if [ -n "$LARGE_FILES" ]; then
    echo -e "${YELLOW}⚠ File PHP molto grandi trovati (>100KB):${NC}"
    echo "$LARGE_FILES"
    ((WARNINGS++))
else
    echo -e "${GREEN}✓ Nessun file eccessivamente grande${NC}"
fi

# 8. Verifica permessi file
echo -e "\n${BLUE}[8/8]${NC} Controllo permessi file..."
WRITABLE=$(find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" -perm -200 -type f | wc -l)
TOTAL_PHP=$(find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" -type f | wc -l)

if [ "$WRITABLE" -eq "$TOTAL_PHP" ]; then
    echo -e "${GREEN}✓ Permessi file corretti${NC}"
else
    echo -e "${YELLOW}⚠ Alcuni file non sono scrivibili${NC}"
    ((WARNINGS++))
fi

# Statistiche finali
echo -e "\n${BLUE}========================================${NC}"
echo -e "${BLUE}Riepilogo${NC}"
echo -e "${BLUE}========================================${NC}"

TOTAL_PHP_FILES=$(find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" | wc -l)
TOTAL_LINES=$(find "$PLUGIN_DIR/fp-multilanguage" -name "*.php" -exec wc -l {} + | tail -1 | awk '{print $1}')

echo -e "File PHP totali: $TOTAL_PHP_FILES"
echo -e "Righe di codice: $TOTAL_LINES"
echo -e ""

if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}✗ Errori critici: $ERRORS${NC}"
fi

if [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}⚠ Warning: $WARNINGS${NC}"
fi

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ Plugin in ottima salute!${NC}"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠ Plugin OK con alcuni warning${NC}"
    exit 0
else
    echo -e "${RED}✗ Azione richiesta per errori critici${NC}"
    exit 1
fi
