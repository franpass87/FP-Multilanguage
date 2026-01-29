# Nuova Strategia Anti-Loop Infinito

## Problema Identificato
Il plugin `FP-SEO-AutoIndex` chiama ripetutamente `do_action('on_publish')`, causando un loop infinito che esaurisce la memoria PHP (512MB). Non è possibile impedire che altri plugin chiamino `do_action()`.

## Soluzioni Tentate (Fallite)
1. ❌ Hook 'all' con priorità -99999
2. ❌ Hook 'on_publish' con priorità -9999
3. ❌ Rimozione da `$wp_filter` e `$wp_actions`
4. ❌ Soglia ultra-aggressiva (0.3 secondi)

**Motivo del fallimento**: Non possiamo impedire che altri plugin chiamino `do_action('on_publish')`.

## Nuova Strategia: Disabilitazione Totale

### Principio
**NON reagire a nessun hook durante la pubblicazione di un post.**

### Implementazione
1. **Rimuovere TUTTI gli hook** che potrebbero causare interazioni:
   - `save_post`
   - `publish_post`
   - `publish_page`
   - `on_publish`
   - `all`
   - `created_term`
   - `edited_term`
   - etc.

2. **Sostituire con un unico hook minimo**:
   - `save_post` con priorità 999
   - NON crea traduzioni automaticamente
   - NON enqueue job
   - NON sincronizza nulla

3. **Le traduzioni vengono create SOLO manualmente**:
   - Tramite il pulsante "Traduci in Inglese ORA"
   - Nessuna traduzione automatica durante save_post

### Vantaggi
- ✅ Nessun loop infinito possibile
- ✅ Nessuna interazione con altri plugin durante la pubblicazione
- ✅ Codice semplice e pulito
- ✅ Facile da debuggare

### Svantaggi
- ⚠️ Le traduzioni devono essere create manualmente
- ⚠️ Nessuna traduzione automatica al salvataggio

### Codice Implementato

```php
if ( ! $this->assisted_mode ) {
    // NUOVA STRATEGIA: disabilita TUTTI gli hook di FP-Multilanguage durante la pubblicazione
    // per evitare loop infiniti causati da altri plugin (es. FP-SEO-AutoIndex)
    add_action( 'save_post', array( $this, 'handle_save_post_safe' ), 999, 3 );
    
    // Hook minimo per enqueue jobs dopo la traduzione manuale
    add_action( 'fpml_after_translation_saved', array( $this, 'enqueue_jobs_after_translation' ), 10, 2 );
}
```

```php
public function handle_save_post_safe( $post_id, $post, $update ) {
    // NUOVA STRATEGIA: NON creare traduzioni automaticamente durante save_post
    // Questo previene loop infiniti causati da altri plugin
    // Le traduzioni devono essere create manualmente tramite "Traduci ORA"
    
    // Protezione base
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( 'auto-draft' === $post->post_status ) {
        return;
    }

    // Se è una traduzione, salta
    if ( get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
        return;
    }

    // Se è in modalità assistita, salta
    if ( $this->is_assisted_mode() ) {
        return;
    }

    // NON fare altro - le traduzioni saranno create solo manualmente
    // Questo previene loop infiniti
    Logger::debug( 'save_post (safe mode) - no automatic translation', array(
        'post_id' => $post_id,
        'post_type' => $post->post_type
    ) );
}
```

## Risultato Atteso
- ✅ Nessun errore HTTP 500
- ✅ Nessun esaurimento memoria
- ✅ Pubblicazione immediata e veloce
- ✅ Traduzioni funzionanti (quando create manualmente)

## Prossimi Passi
1. Test di pubblicazione di una nuova pagina
2. Test di traduzione manuale tramite "Traduci ORA"
3. Verifica che non ci siano loop infiniti

