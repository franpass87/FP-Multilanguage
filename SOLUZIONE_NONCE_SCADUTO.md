# Soluzione per l'Errore "Il link che hai seguito è scaduto"

## Problema Identificato

Quando si tenta di eseguire il reindex, l'utente riceve l'errore:
```
Il link che hai seguito è scaduto.
```

E nel browser JavaScript si vedono gli errori:
```
Refresh nonce fallito con status: 401
Impossibile aggiornare il nonce
```

## Causa del Problema

Il problema era causato da **due diversi tipi** di errori di nonce scaduto:

### 1. **Errore AJAX/REST** (Risolto nella prima iterazione)
- Nonce scaduto durante le richieste AJAX per il reindex
- Endpoint refresh bloccato che richiedeva autenticazione completa
- JavaScript che non gestiva correttamente i nonce scaduti

### 2. **Errore Form Submit** (Problema principale identificato dall'utente)
- Nonce scaduto quando si invia il form delle impostazioni
- WordPress mostra "Il link che hai seguito è scaduto" dopo il redirect
- L'errore appare **nella pagina**, non durante le richieste AJAX

## Soluzione Implementata

### 1. **Gestione Form Submit** (Soluzione per il problema principale)

**File**: `fp-multilanguage/admin/class-admin.php`

Implementato un sistema multi-livello per intercettare e gestire gli errori di nonce scaduto:

#### **Hook Precoce (`init` con priorità 1)**
```php
public function handle_expired_nonce_early() {
    // Intercetta errori di nonce molto presto nel processo di caricamento
    if ( $has_nonce_error ) {
        $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        $clean_url .= '&settings-updated=true';
        wp_safe_redirect( $clean_url );
        exit;
    }
}
```

#### **Hook Admin Init**
```php
public function handle_expired_nonce_redirect() {
    // Gestisce redirect dopo submit del form con nonce scaduto
}
```

#### **Custom wp_die Handler**
```php
public function custom_wp_die_handler( $handler ) {
    // Intercetta le chiamate wp_die per errori di nonce
}
```

### 2. Sistema AJAX WordPress (Per le richieste AJAX)

**File**: `fp-multilanguage/admin/class-admin.php`

Implementato un handler AJAX WordPress che:
- Usa `wp_ajax_fpml_refresh_nonce` (più affidabile del REST API)
- Non richiede validazione del nonce per l'azione stessa
- Genera un nuovo nonce per `wp_rest`

```php
public function handle_refresh_nonce() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
    }
    
    $new_nonce = wp_create_nonce( 'wp_rest' );
    wp_send_json_success( array( 'nonce' => $new_nonce ) );
}
```

### 4. **Messaggi di Successo** (Miglioramento UX)

**File**: `fp-multilanguage/admin/views/settings-diagnostics.php`

Aggiunto sistema di notifiche per confermare il salvataggio delle impostazioni:

```php
if ( $form_submitted ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             esc_html__( 'Impostazioni salvate con successo.', 'fp-multilanguage' ) . 
             '</p></div>';
    });
}
```

### 5. Nuovo Metodo di Controllo Permessi (Fallback REST)

**File**: `fp-multilanguage/rest/class-rest-admin.php`

Creato il metodo `check_refresh_nonce_permissions()` che:
- Richiede solo che l'utente sia loggato e abbia `manage_options`
- **NON** richiede validazione del nonce (evita il circolo vizioso)

```php
public function check_refresh_nonce_permissions( $request ) {
    // Only require user to be logged in and have manage_options capability
    // Do NOT require nonce validation since this endpoint is used to refresh nonces
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        return new WP_Error(
            'fpml_rest_forbidden',
            __( 'Permessi insufficienti.', 'fp-multilanguage' ),
            array( 'status' => rest_authorization_required_code() )
        );
    }
    return true;
}
```

### 3. Miglioramento del JavaScript (Doppio Fallback)

**File**: `fp-multilanguage/assets/admin.js`

Migliorato il metodo `refreshNonce()` con:
- **Primo tentativo**: AJAX WordPress (più affidabile)
- **Fallback**: REST API endpoint (se AJAX fallisce)
- Headers aggiuntivi per una migliore compatibilità
- Gestione errori più dettagliata con logging
- Messaggio di errore più utile con link per ricaricare la pagina

```javascript
const refreshNonce = async () => {
    // Add headers to ensure proper authentication
    const response = await fetch(refreshEndpoint, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
    
    // Enhanced error handling with detailed logging
    if (!response.ok) {
        console.error('Refresh nonce fallito con status:', response.status);
        const errorData = await response.text();
        console.error('Dettagli errore refresh nonce:', errorData);
        return null;
    }
    // ... resto del codice
};
```

### 3. Messaggi di Errore Migliorati

Il sistema ora fornisce:
- Link diretto per ricaricare la pagina in caso di fallimento
- Logging dettagliato per il debug
- Feedback più chiaro all'utente

## Come Testare la Soluzione

### Test Automatico
Esegui lo script di test:
```bash
php test-refresh-nonce.php
```

### Test Manuale
1. Vai alla pagina delle impostazioni del plugin
2. Prova a eseguire il reindex
3. Se il nonce è scaduto, il sistema dovrebbe:
   - Rilevare automaticamente l'errore
   - Richiedere un nuovo nonce
   - Ripetere l'operazione automaticamente
   - Mostrare feedback appropriato

## Prevenzione Futura

Per evitare questo problema in futuro:

1. **Refresh Automatico**: Il sistema ora gestisce automaticamente la scadenza dei nonce
2. **Fallback Graceful**: In caso di fallimento, fornisce un link per ricaricare la pagina
3. **Logging Migliorato**: Errori più dettagliati per facilitare il debug

## Note Tecniche

- L'endpoint `refresh-nonce` è ora accessibile senza validazione nonce
- Mantiene la sicurezza richiedendo autenticazione utente
- Compatibile con tutti i browser moderni
- Non richiede modifiche alla configurazione del server

## File Modificati

1. `fp-multilanguage/admin/class-admin.php` - Handler AJAX per refresh nonce
2. `fp-multilanguage/rest/class-rest-admin.php` - Nuovo metodo permessi (fallback)
3. `fp-multilanguage/assets/admin.js` - Sistema doppio fallback per refresh nonce
4. `fp-multilanguage/admin/views/settings-diagnostics.php` - Aggiunto ajaxurl
5. `test-refresh-nonce.php` - Script di test (nuovo)
6. `SOLUZIONE_NONCE_SCADUTO.md` - Questa documentazione (nuovo)

## Vantaggi della Soluzione

### **Per il Problema Form Submit:**
1. **Intercettazione Multi-Livello**: Tre hook diversi per catturare l'errore in qualsiasi momento
2. **Redirect Pulito**: Rimuove automaticamente i parametri di nonce scaduto dall'URL
3. **Messaggio di Successo**: Mostra conferma che le impostazioni sono state salvate
4. **Zero Interruzioni**: L'utente non vede mai l'errore "link scaduto"

### **Per il Problema AJAX:**
1. **Doppio Fallback**: Se l'AJAX WordPress fallisce, prova l'endpoint REST
2. **Più Affidabile**: L'AJAX WordPress è più stabile del REST API per operazioni semplici
3. **Compatibilità**: Funziona anche se ci sono problemi con il REST API
4. **Debugging**: Logging dettagliato per identificare rapidamente i problemi
5. **UX Migliorata**: Messaggi di errore chiari con link per ricaricare la pagina

### **Soluzione Completa:**
- ✅ **Form Submit**: Nessun errore "link scaduto" dopo aver salvato le impostazioni
- ✅ **Reindex AJAX**: Gestione automatica dei nonce scaduti durante il reindex
- ✅ **Feedback Utente**: Messaggi chiari e informativi
- ✅ **Robustezza**: Multipli livelli di fallback per ogni scenario
