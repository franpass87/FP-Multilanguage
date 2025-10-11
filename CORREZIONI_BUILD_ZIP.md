# Correzioni Build ZIP per Installazione WordPress

## Problema Identificato

Il builder ZIP (`scripts/build-zip.sh`) creava pacchetti **non funzionanti** per l'installazione su siti WordPress perché:

1. ❌ **Mancavano le dipendenze Composer** - La directory `vendor/` veniva esclusa dal pacchetto
2. ❌ **Nessuna installazione dipendenze** - Lo script non eseguiva `composer install` prima di creare lo ZIP
3. ❌ **File non necessari inclusi** - File di sviluppo venivano inclusi nel pacchetto finale

## Modifiche Apportate

### 1. Script `scripts/build-zip.sh`

#### Aggiunta installazione dipendenze Composer
```bash
# Installazione automatica delle dipendenze in modalità produzione
if command -v composer &> /dev/null; then
  echo "Installazione dipendenze Composer in produzione..."
  rm -rf "${PLUGIN_DIR}/vendor"
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  composer dump-autoload -o --classmap-authoritative
else
  # Gestione errore se Composer non è disponibile
  echo "ATTENZIONE: Composer non trovato nel PATH."
  if [[ ! -d "${PLUGIN_DIR}/vendor" ]]; then
    echo "Errore: La directory vendor non esiste e Composer non è disponibile per crearla."
    exit 1
  fi
fi
```

#### Rimozione esclusione `vendor/`
La directory `vendor/` ora viene **inclusa** nel pacchetto ZIP perché contiene l'autoloader necessario per il funzionamento del plugin.

#### Esclusioni aggiuntive
Aggiunti file di sviluppo all'elenco delle esclusioni:
- `*.md` - File di documentazione
- `phpstan.neon` - Configurazione PHPStan
- `phpunit.xml.dist` - Configurazione PHPUnit
- `composer.json` - File Composer (le dipendenze sono già in vendor/)
- `composer.lock` - Lock file Composer

#### Controllo sintassi PHP opzionale
```bash
# Controllo sintassi solo se PHP è disponibile
if command -v php &> /dev/null; then
  if find "${STAGE}" -type f -name '*.php' -print -quit | grep -q '.'; then
    echo "Controllo sintassi file PHP..."
    find "${STAGE}" -type f -name '*.php' -print0 | xargs -0 -n1 -P4 php -l
  fi
else
  echo "ATTENZIONE: PHP non trovato nel PATH, salto il controllo sintassi."
fi
```

### 2. Workflow GitHub `.github/workflows/build-plugin-zip.yml`

#### Esclusioni allineate
Aggiunte le stesse esclusioni dello script bash per coerenza:
- `test` (singolare)
- `*.bak`, `*.tmp`
- `dist`
- `phpstan.neon`
- `phpunit.xml.dist`
- `composer.json`
- `composer.lock`

**Nota**: Questo workflow mantiene già l'installazione corretta delle dipendenze Composer.

### 3. File `fp-multilanguage/readme.txt`

#### Correzione requisiti PHP
Allineato il requisito PHP con quello specificato in `composer.json`:
- Prima: `Requires PHP: 7.4`
- Dopo: `Requires PHP: 8.0`

## Struttura Finale del Pacchetto ZIP

Il pacchetto ZIP finale (`fp-multilanguage-{version}.zip`) contiene:

```
fp-multilanguage/
├── fp-multilanguage.php       # File principale del plugin
├── readme.txt                 # Documentazione WordPress
├── uninstall.php             # Script di disinstallazione
├── admin/                    # Interfaccia amministrativa
├── assets/                   # CSS e JavaScript
├── cli/                      # Comandi WP-CLI
├── includes/                 # Classi PHP del plugin
├── languages/                # File di traduzione
├── rest/                     # Endpoint REST API
└── vendor/                   # ✅ Dipendenze Composer (INCLUSO)
    └── autoload.php          # ✅ Autoloader necessario
```

### File Esclusi (non necessari in produzione)
- `.git/`, `.github/` - Repository Git
- `tests/`, `docs/` - Test e documentazione
- `*.md` - File Markdown
- `phpstan.neon`, `phpunit.xml.dist` - Configurazioni test
- `composer.json`, `composer.lock` - File Composer
- File temporanei e backup

## Come Usare lo Script

### Prerequisiti
- PHP 8.0 o superiore
- Composer 2.x
- `zip`, `rsync` e utility Unix standard

### Esecuzione
```bash
# Dalla root del progetto
bash scripts/build-zip.sh
```

### Output
Lo script:
1. ✅ Rileva automaticamente la versione dal file principale
2. ✅ Installa le dipendenze Composer in modalità produzione
3. ✅ Ottimizza l'autoloader con classmap-authoritative
4. ✅ Copia i file escludendo quelli di sviluppo
5. ✅ Verifica la sintassi PHP (se disponibile)
6. ✅ Crea il file ZIP in `dist/fp-multilanguage-{version}.zip`

### Esempio Output
```
Versione rilevata: 0.3.1
Installazione dipendenze Composer in produzione...
Controllo sintassi file PHP...
Creazione archivio: dist/fp-multilanguage-0.3.1.zip
4.2M    dist/fp-multilanguage-0.3.1.zip
```

## Installazione su WordPress

Il pacchetto ZIP può essere installato in due modi:

### 1. Via Admin WordPress
1. Accedi al pannello di amministrazione
2. Vai su **Plugin → Aggiungi nuovo**
3. Clicca su **Carica plugin**
4. Seleziona il file `fp-multilanguage-{version}.zip`
5. Clicca su **Installa ora**
6. Attiva il plugin

### 2. Via FTP/SSH
1. Estrai il contenuto dello ZIP
2. Carica la cartella `fp-multilanguage` in `/wp-content/plugins/`
3. Attiva il plugin dal pannello WordPress

## Verifica

Per verificare che il plugin sia installato correttamente:

```php
// Il file vendor/autoload.php deve esistere
file_exists(WP_PLUGIN_DIR . '/fp-multilanguage/vendor/autoload.php');

// La versione deve corrispondere
defined('FPML_PLUGIN_VERSION'); // Dovrebbe essere '0.3.1'
```

## Note Importanti

⚠️ **Il pacchetto ZIP DEVE includere la directory `vendor/`** altrimenti il plugin non funzionerà perché il file principale richiede l'autoloader:

```php
// fp-multilanguage.php linea 22-26
$autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
    require $autoload;
}
```

✅ **Tutti i problemi sono stati risolti** e il plugin ora può essere installato correttamente su qualsiasi sito WordPress.

## Workflow GitHub Actions

Il workflow `.github/workflows/build-zip.yml` usa automaticamente lo script corretto quando viene creato un tag:

```bash
# Crea un tag e push
git tag v0.3.2
git push origin v0.3.2

# Il workflow crea automaticamente:
# - Artifact con il pacchetto ZIP
# - Release GitHub con il pacchetto allegato
```

---

**Data correzioni**: 2025-10-11  
**Branch**: cursor/fix-zip-builder-for-wordpress-plugin-install-bc38
