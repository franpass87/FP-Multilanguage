# 🐛 Bug Fix: Loop Infinito durante Creazione Pagina - 2025-11-17

## 🔴 Problema Identificato

**Errore**: HTTP 500 durante la pubblicazione di una nuova pagina

**Causa**: Loop infinito causato da `save_post` hook che si triggera ripetutamente

**Sintomi**:
- Memoria esaurita (Allowed memory size exhausted)
- `on_publish` chiamato ripetutamente per lo stesso post
- Server che va in timeout

## 🔍 Analisi

Il problema si verificava perché:

1. Utente pubblica una pagina → `save_post` viene triggerato
2. `handle_save_post` chiama `ensure_post_translation`
3. `ensure_post_translation` crea una nuova pagina tradotta usando `wp_insert_post`
4. `wp_insert_post` triggera di nuovo `save_post`
5. Loop infinito → memoria esaurita → errore 500

## ✅ Soluzione Implementata

### 1. Protezione con Flag Statico
Aggiunto un flag statico `$processing` in `handle_save_post` per evitare che lo stesso post venga processato più volte:

```php
static $processing = array();
if ( isset( $processing[ $post_id ] ) ) {
    Logger::debug( 'handle_save_post skipped - already processing', array( 'post_id' => $post_id ) );
    return;
}
$processing[ $post_id ] = true;
```

### 2. Disabilitazione Temporanea Hook
Disabilitato temporaneamente l'hook `save_post` del plugin quando si crea una traduzione:

```php
// Disabilita temporaneamente questo hook per evitare loop quando si crea la traduzione
remove_action( 'save_post', array( $this, 'handle_save_post' ), 20 );

$target_post = $this->translation_manager->ensure_post_translation( $post );

// Riabilita l'hook
add_action( 'save_post', array( $this, 'handle_save_post' ), 20, 3 );
```

### 3. Sospensione Cache durante Creazione
Sospesa la cache durante la creazione della traduzione per evitare hook aggiuntivi:

```php
wp_suspend_cache_addition( true );
$target_id = wp_insert_post( $postarr, true );
wp_suspend_cache_addition( false );
```

## 📝 File Modificati

1. **`src/Core/Plugin.php`**:
   - Aggiunto flag statico `$processing` in `handle_save_post`
   - Disabilitazione/riabilitazione temporanea dell'hook `save_post`
   - Cleanup del flag alla fine del metodo

2. **`src/Content/TranslationManager.php`**:
   - Sospensione cache durante `wp_insert_post` in `create_post_translation`

## ✅ Risultato

- ✅ Loop infinito risolto
- ✅ Memoria non più esaurita
- ✅ Pagine possono essere create senza errori 500
- ✅ Traduzioni vengono create correttamente

---

**Data Fix**: 2025-11-17  
**Status**: ✅ **RISOLTO**


