# Plugin Audit Report — FP Multilanguage — 2025-10-01

## Summary
- Files scanned: 39/39
- Issues found: 4 (Critical: 0 | High: 2 | Medium: 2 | Low: 0)
- Status: Audit complete
- Key risks:
  - Opzioni archiviate con autoload che possono crescere a centinaia di KB e saturare la cache oggetti in frontend.
  - Cambio di routing /en/↔?lang=en che non invalida le rewrite, causando 404 finché non si salva manualmente permalink.
  - Override manuali che sanitizzano le traduzioni con `sanitize_text_field()`, eliminando markup HTML necessario.
  - Import CSV per override/glossario/log fragile con valori multilinea che vengono troncati.
- Recommended priorities:
  1. Mettere in sicurezza l'autoload delle opzioni voluminose.
  2. Gestire il flush delle rewrite quando cambia la modalità di routing.
  3. Consentire markup sicuro nelle override per preservare il testo tradotto.
  4. Rendere robusta la lettura CSV per payload multilinea.

## Manifest mismatch
- Stored hash: `7696830e005fe8542c1848708ec8e846bd19c77d5a8061b2b72122ce72f8f93d`
- Rebuilt hash: `1b0cfd658baa0901057751ea197935c2ae681b9e314a64486e8d645d08470aa4`
- Action: ricostruito il manifest ordinato dei 39 file del plugin (PHP, asset e cataloghi) e aggiornato lo stato per riflettere il nuovo hash.

## Issues
### [High] Opzioni voluminose salvate con autoload attivo
- ID: ISSUE-001
- File: fp-multilanguage/includes/class-strings-scanner.php:90 (anche class-strings-override.php:86, class-glossary.php:99)
- Snippet:
  ```php
  protected function save_catalog( $catalog ) {
      update_option( self::OPTION_KEY, $catalog );
      $this->catalog = $catalog;
  }
  ```

Diagnosis: catalogo stringhe, override e glossario vengono salvati con `update_option()` senza specificare `autoload => false`. Queste strutture possono contenere centinaia/migliaia di voci (scansione di tema/plugin, glossari lunghi) e vengono quindi caricate su ogni richiesta front-end, peggiorando drasticamente tempi e memoria su hosting condivisi.

Impact: performance (memoria e tempo di risposta), rischio di superare limiti di memory limit/opcache e saturare l'object cache predefinita.

Proposed fix (concise):

```
update_option( self::OPTION_KEY, $catalog, false );
```

Applicare la stessa logica a overrides/glossario e, in caso di installazioni esistenti, rieseguire un `update_option( key, get_option( key ), false )` per aggiornare il flag.

Side effects / Regression risk: basso, i dati restano identici; verificare solo che le chiamate di lettura continuino a funzionare.

Est. effort: M

Tags: #performance #autoload #options

### [High] Cambio routing lingua non invalida le rewrite
- ID: ISSUE-002
- File: fp-multilanguage/includes/class-settings.php:208
- Snippet:
  ```php
  $data['routing_mode'] = in_array( $data['routing_mode'], array( 'segment', 'query' ), true )
      ? $data['routing_mode'] : $defaults['routing_mode'];
  ```

Diagnosis: quando si passa da `query` a `segment` (o viceversa) nel pannello, l'opzione viene salvata ma non viene invocato `flush_rewrite_rules()`. Le regole `add_rewrite_rule('^en/...')` restano quindi assenti (o stale) fino a un flush manuale dei permalink, generando 404 sulla lingua inglese appena si cambia modalità.

Impact: funzionale (routing), UX amministratore.

Repro steps:
1. Impostare modalità routing su `query` e salvare.
2. Successivamente impostare `segment` e salvare.
3. Visitare `/en/` → 404 finché non si salva manualmente “Permalink”.

Proposed fix (concise):

```
add_action( 'update_option_fpml_settings', function ( $old, $new ) {
    if ( isset( $old['routing_mode'], $new['routing_mode'] ) && $old['routing_mode'] !== $new['routing_mode'] ) {
        FPML_Rewrites::instance()->register_rewrites();
        flush_rewrite_rules( false );
    }
}, 10, 2 );
```

Side effects / Regression risk: basso; il flush avviene solo su cambiamento effettivo dell'opzione.

Est. effort: S

Tags: #routing #rewrite #ux

### [Medium] Import CSV non gestisce campi multilinea
- ID: ISSUE-003
- File: fp-multilanguage/includes/class-export-import.php:649
- Snippet:
  ```php
  $lines = preg_split( '/\r\n|\r|\n/', $csv );
  ...
  $columns = str_getcsv( $line );
  ```

Diagnosis: Il parser CSV splitta prima sui newline e solo dopo usa `str_getcsv`. Se un campo è racchiuso tra virgolette e contiene newline (caso frequente per contenuti HTML o descrizioni lunghe), i dati vengono spezzati e l'import fallisce/parzializza righe.

Impact: funzionale (import override/glossario/log), perdita di dati, UX admin.

Proposed fix (concise):

```
$handle = fopen( 'php://temp', 'r+' );
fwrite( $handle, $csv );
rewind( $handle );
$header = fgetcsv( $handle );
while ( ( $columns = fgetcsv( $handle ) ) !== false ) { ... }
```

Side effects / Regression risk: basso; assicura compatibilità anche con CSV esistenti.

Est. effort: M

Tags: #import #csv #admin

### [Medium] Override delle stringhe rimuovono markup HTML valido
- ID: ISSUE-004
- File: fp-multilanguage/includes/class-strings-override.php:136
- Snippet:
  ```php
  $target = isset( $row['target'] ) ? sanitize_text_field( $row['target'] ) : '';
  ...
  $target  = sanitize_text_field( $target );
  ```

Diagnosis: sia `update_overrides()` sia `add_override()` sanificano i testi delle traduzioni con `sanitize_text_field()`. La funzione elimina tag HTML, newline e una parte della punteggiatura, rendendo impossibile salvare override che contengono link, markup o formattazione prevista da WordPress/Core (es. `<a>`, `<strong>`, `&nbsp;`).

Impact: funzionale/i18n — gli amministratori perdono markup essenziale nelle traduzioni personalizzate e i contenuti risultano monchi lato frontend.

Proposed fix (concise):

```
$target = isset( $row['target'] ) ? wp_kses_post( $row['target'] ) : '';
...
$target = wp_kses_post( $target );
```

Mantenere `sanitize_text_field()` solo per il campo `context` e assicurare l'escaping in output (`esc_attr`, `esc_html`) come già presente nelle view admin.

Side effects / Regression risk: basso; `wp_kses_post()` rimuove markup insicuro ma conserva quello ammesso da WordPress, evitando regressioni di sicurezza.

Est. effort: S

Tags: #i18n #admin #data-integrity

## Conflicts & Duplicates
Nessuna duplicazione rilevata.

## Deprecated & Compatibility
Nessuna API deprecata rilevata; nessun warning PHP 8.2/8.3 individuato durante la revisione statica.

## Performance Hotspots
- ISSUE-001: opzioni autoloadate con payload potenzialmente molto grande.

## i18n & A11y
- ISSUE-004: le override perdono markup HTML a causa di una sanificazione eccessiva.

## Test Coverage
Non sono presenti riferimenti a test automatici per le aree analizzate.

## Next Steps (per fase di FIX)
Ordine consigliato: ISSUE-001 → ISSUE-002 → ISSUE-004 → ISSUE-003

Safe-fix batch plan:
- Lotto 1: ISSUE-001 (tutte le opzioni autoload) + test regressione backend.
- Lotto 2: ISSUE-002 (flush rewrite su cambio routing) con verifica su permalink.
- Lotto 3: ISSUE-004 (sanitizzazione override con markup) + test su override con link/formattazione.
- Lotto 4: ISSUE-003 (parser CSV) con import/export di prova.
