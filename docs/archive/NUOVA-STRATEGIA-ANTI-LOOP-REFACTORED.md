# Nuova Strategia Anti-Loop Refactored

**Data**: 2025-11-17  
**Versione**: 0.9.4

## Problema

Il plugin `FP-SEO-AutoIndex` chiama ripetutamente `do_action('on_publish')`, causando un loop infinito che esaurisce la memoria PHP (512MB) e genera errori HTTP 500.

## Strategia Implementata

### 1. Hook 'all' con Priorità Massima (-99999)

**File**: `src/Core/Plugin.php` - Metodo `handle_all_hooks()`

**Funzionamento**:
- Intercetta **TUTTE** le chiamate a `do_action()` PRIMA che `do_action` controlli se l'hook esiste (riga 498 di `wp-includes/plugin.php`)
- Se un loop è rilevato, rimuove **COMPLETAMENTE** l'hook `on_publish` da `$wp_filter` e `$wp_actions`
- Quando `do_action` controlla se l'hook esiste (riga 498), non lo trova e fa `return` immediatamente

**Codice Chiave**:
```php
public function handle_all_hooks( $hook_name, $arg1 = null, ... ) {
    if ( 'on_publish' !== $hook_name ) {
        return;
    }
    
    $post_id = is_numeric( $arg1 ) ? (int) $arg1 : 0;
    
    // Se bloccato globalmente, rimuovi IMMEDIATAMENTE
    if ( isset( $GLOBALS['fpml_block_on_publish'] ) && $GLOBALS['fpml_block_on_publish'] ) {
        global $wp_filter, $wp_actions;
        if ( isset( $wp_filter['on_publish'] ) ) {
            unset( $wp_filter['on_publish'] );
        }
        if ( isset( $wp_actions['on_publish'] ) ) {
            unset( $wp_actions['on_publish'] );
        }
        return;
    }
    
    // Rilevamento loop preventivo: se più di 1 chiamata in 0.2 secondi, BLOCCA
    static $on_publish_pre_count = array();
    $current_time = microtime( true );
    
    if ( $post_id > 0 ) {
        if ( ! isset( $on_publish_pre_count[ $post_id ] ) ) {
            $on_publish_pre_count[ $post_id ] = array();
        }
        
        // Rimuovi chiamate più vecchie di 0.2 secondi
        $on_publish_pre_count[ $post_id ] = array_filter( $on_publish_pre_count[ $post_id ], function( $time ) use ( $current_time ) {
            return ( $current_time - $time ) < 0.2;
        } );
        
        $on_publish_pre_count[ $post_id ][] = $current_time;
        
        // Se anche solo 1 chiamata ripetuta in 0.2 secondi, BLOCCA IMMEDIATAMENTE
        if ( count( $on_publish_pre_count[ $post_id ] ) > 1 ) {
            $GLOBALS['fpml_block_on_publish'] = true;
            set_transient( 'fpml_blocked_hooks_' . $post_id, true, 15 );
            
            global $wp_filter, $wp_actions;
            if ( isset( $wp_filter['on_publish'] ) ) {
                unset( $wp_filter['on_publish'] );
            }
            if ( isset( $wp_actions['on_publish'] ) ) {
                unset( $wp_actions['on_publish'] );
            }
            
            wp_schedule_single_event( time() + 15, 'fpml_restore_on_publish' );
            return;
        }
    }
}
```

### 2. Hook 'on_publish' con Priorità -9999

**File**: `src/Core/Plugin.php` - Metodo `handle_on_publish()`

**Funzionamento**:
- Rileva loop quando `on_publish` viene effettivamente eseguito
- Rimuove specificamente gli hook di `FP-SEO-AutoIndex` da `$wp_filter['on_publish']`
- Blocca globalmente `on_publish` se rileva più di 1 chiamata in 0.5 secondi

### 3. Hook 'publish_post'/'publish_page' con Priorità 1

**File**: `src/Core/Plugin.php` - Metodo `handle_publish_post()`

**Funzionamento**:
- Rileva loop da altri plugin che chiamano `publish_post` ripetutamente
- Se più di 3 chiamate in 5 secondi, disabilita globalmente gli hook problematici:
  - `publish_post`
  - `transition_post_status`
  - `publish_page`
  - `on_publish`

### 4. Hook 'save_post' con Protezione Ultra-Aggressiva

**File**: `src/Core/Plugin.php` - Metodo `handle_save_post()`

**Funzionamento**:
- Rate limiting: max 1 chiamata ogni 3 secondi per post
- Contatore chiamate: max 2 chiamate in 10 secondi
- Se troppe chiamate, disabilita globalmente gli hook problematici per 10 secondi

## Vantaggi della Nuova Strategia

1. **Intercettazione Preventiva**: L'hook `all` viene chiamato PRIMA che `do_action` controlli se l'hook esiste, permettendo di rimuoverlo completamente
2. **Rilevamento Ultra-Rapido**: Soglia di 0.2 secondi per rilevare loop immediatamente
3. **Blocco Globale**: Una volta rilevato un loop, blocca globalmente `on_publish` per tutti i post
4. **Ripristino Automatico**: Ripristina gli hook dopo 15 secondi usando `wp_schedule_single_event`

## Come Funziona WordPress do_action()

```php
function do_action( $hook_name, ...$arg ) {
    global $wp_filter, $wp_actions, $wp_current_filter;
    
    // Incrementa contatore
    if ( ! isset( $wp_actions[ $hook_name ] ) ) {
        $wp_actions[ $hook_name ] = 1;
    } else {
        ++$wp_actions[ $hook_name ];
    }
    
    // Do 'all' actions first (QUI viene chiamato handle_all_hooks)
    if ( isset( $wp_filter['all'] ) ) {
        $wp_current_filter[] = $hook_name;
        $all_args = func_get_args();
        _wp_call_all_hook( $all_args );
    }
    
    // Controlla se l'hook esiste (QUI viene controllato dopo che abbiamo rimosso l'hook)
    if ( ! isset( $wp_filter[ $hook_name ] ) ) {
        if ( isset( $wp_filter['all'] ) ) {
            array_pop( $wp_current_filter );
        }
        return; // <-- QUI fa return se l'hook non esiste
    }
    
    // Esegue l'hook
    $wp_filter[ $hook_name ]->do_action( $arg );
    
    array_pop( $wp_current_filter );
}
```

## Test

Per testare la nuova strategia:

1. Crea una nuova pagina/articolo
2. Pubblica la pagina/articolo
3. Verifica che non ci siano errori HTTP 500
4. Controlla i log per vedere se i loop vengono rilevati e bloccati

## Note

- La strategia è **ultra-aggressiva** e blocca `on_publish` immediatamente se rileva un loop
- Il blocco dura 15 secondi, poi viene ripristinato automaticamente
- Se `FP-SEO-AutoIndex` continua a chiamare `do_action('on_publish')` anche dopo che l'hook è stato rimosso, `do_action` farà semplicemente `return` senza eseguire nulla


