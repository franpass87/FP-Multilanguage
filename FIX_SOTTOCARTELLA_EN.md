# Fix: Creazione Sottocartella /en/ per Pagine Tradotte

## 🎯 Problema Risolto

Il sistema non generava correttamente gli URL con il prefisso `/en/` per le pagine tradotte. Le pagine venivano create con slug tipo `en-nome-pagina` ma gli URL non venivano convertiti in `/en/nome-pagina/`.

## 🔧 Modifiche Effettuate

### 1. Migliorato il Filtro Permalink (`class-language.php`)

**File modificato:** `fp-multilanguage/includes/class-language.php`

Il filtro `filter_translation_permalink()` è stato migliorato per:

- ✅ Gestire correttamente le pagine gerarchiche (con parent)
- ✅ Evitare duplicazione del prefisso `/en/` negli URL
- ✅ Mantenere la struttura gerarchica completa negli URL
- ✅ Applicare il trailing slash corretto secondo le impostazioni di WordPress

**Prima:**
```php
// Generava: /en-about/
if ( 0 === strpos( $post->post_name, 'en-' ) ) {
    $base_slug = substr( $post->post_name, 3 );
    $home_url = home_url( '/' );
    $permalink = $home_url . 'en/' . $base_slug . '/';
}
```

**Dopo:**
```php
// Genera: /en/about/
// Gestisce anche pagine figlie: /en/parent/child/
if ( 0 === strpos( $post->post_name, 'en-' ) ) {
    $base_slug = substr( $post->post_name, 3 );
    
    // Gestisce gerarchia parent
    $parent_permalink = '';
    if ( $post->post_parent > 0 ) {
        $parent = get_post( $post->post_parent );
        if ( $parent instanceof WP_Post ) {
            if ( get_post_meta( $parent->ID, '_fpml_is_translation', true ) ) {
                $parent_permalink = $this->filter_translation_permalink( get_permalink( $parent ), $parent );
            } else {
                $parent_permalink = get_permalink( $parent );
            }
            $parent_permalink = str_replace( home_url( '/' ), '', trailingslashit( $parent_permalink ) );
        }
    }
    
    // Costruisci URL completo
    $home_url = trailingslashit( home_url() );
    if ( $parent_permalink ) {
        $parent_permalink = str_replace( 'en/', '', $parent_permalink );
        $permalink = $home_url . 'en/' . trailingslashit( $parent_permalink ) . $base_slug . '/';
    } else {
        $permalink = $home_url . 'en/' . $base_slug . '/';
    }
    
    $permalink = user_trailingslashit( $permalink );
}
```

### 2. Aggiunto Hook per Setup Automatico (`class-plugin.php`)

**File modificato:** `fp-multilanguage/includes/core/class-plugin.php`

Aggiunto al costruttore:
```php
// Run setup if needed (includes rewrite rules registration)
add_action( 'init', array( $this, 'maybe_run_setup' ), 5 );
```

Questo assicura che:
- ✅ Le rewrite rules vengano registrate all'attivazione del plugin
- ✅ Il flush delle rewrite rules avvenga automaticamente quando necessario
- ✅ Il sistema sia configurato correttamente anche dopo aggiornamenti

### 3. Script di Test Creato

**File creato:** `test-en-subfolder.php`

Uno script di test completo per verificare:
- ✅ Presenza delle rewrite rules per `/en/`
- ✅ Permalink corretti per tutte le pagine tradotte
- ✅ Configurazione routing mode
- ✅ Registrazione dei filtri
- ✅ Test manuale di singoli post

## 📋 Come Verificare che Funzioni

### Metodo 1: Script di Test (Raccomandato)

1. Accedi al file di test tramite browser:
   ```
   https://tuo-sito.com/wp-content/plugins/fp-multilanguage/test-en-subfolder.php
   ```

2. Lo script mostrerà:
   - ✅ Stato delle rewrite rules
   - ✅ Lista delle pagine tradotte con i loro permalink
   - ✅ Verifica delle impostazioni
   - ✅ Possibilità di testare singoli post

3. Se necessario, clicca su "Flush Rewrite Rules" nello script

### Metodo 2: Verifica Manuale

1. **Vai alla dashboard di WordPress**

2. **Controlla una pagina tradotta:**
   - Vai su "Tutte le pagine"
   - Trova una pagina con slug tipo `en-nome-pagina`
   - Clicca su "Visualizza"
   - L'URL dovrebbe essere: `/en/nome-pagina/`

3. **Se l'URL non è corretto:**
   - Vai su "Impostazioni > Permalink"
   - Clicca su "Salva modifiche" (questo forza il flush delle rewrite rules)
   - Riprova

### Metodo 3: Verifica da WordPress Admin

1. Vai su **FP Multilanguage > Impostazioni**

2. Verifica che il **Routing Mode** sia impostato su **"segment"**

3. Se cambi l'impostazione, salva e poi vai su **Impostazioni > Permalink** e clicca "Salva" per aggiornare le rewrite rules

## 🔍 Risoluzione Problemi

### Problema: Gli URL non hanno ancora /en/

**Soluzione 1: Flush Rewrite Rules**
```php
// Aggiungi questo codice temporaneo in functions.php del tema
add_action( 'init', function() {
    flush_rewrite_rules();
}, 999 );
// IMPORTANTE: Rimuovi questo codice dopo aver visitato il sito una volta!
```

**Soluzione 2: Da Admin**
1. Vai su "Impostazioni > Permalink"
2. Clicca su "Salva modifiche"
3. Verifica di nuovo i permalink

**Soluzione 3: Usa lo Script di Test**
1. Visita `test-en-subfolder.php`
2. Clicca sul pulsante "Flush Rewrite Rules"

### Problema: Routing mode non è "segment"

1. Vai su **FP Multilanguage > Impostazioni > Generale**
2. Trova l'opzione **"Routing Mode"**
3. Seleziona **"URL Segment (/en/)"**
4. Salva
5. Vai su **Impostazioni > Permalink** e salva di nuovo

### Problema: Le pagine tradotte non esistono ancora

Il plugin crea automaticamente le pagine tradotte quando:
1. Salvi o aggiorni una pagina italiana
2. Esegui il reindex dei contenuti

Per forzare la creazione:
1. Vai su **FP Multilanguage > Queue Manager**
2. Clicca su **"Reindex Content"**

## ✅ Esempio di Funzionamento Corretto

### Struttura Pagine:
```
📄 Chi Siamo (slug: chi-siamo)
   └── 📄 Il Team (slug: il-team)
       └── 📄 Contatti (slug: contatti)

📄 Chi Siamo (EN) (slug: en-chi-siamo)
   └── 📄 Il Team (EN) (slug: en-il-team)
       └── 📄 Contatti (EN) (slug: en-contatti)
```

### URL Generati:
```
Italiano:
https://tuo-sito.com/chi-siamo/
https://tuo-sito.com/chi-siamo/il-team/
https://tuo-sito.com/chi-siamo/il-team/contatti/

Inglese:
https://tuo-sito.com/en/chi-siamo/
https://tuo-sito.com/en/chi-siamo/il-team/
https://tuo-sito.com/en/chi-siamo/il-team/contatti/
```

## 🚀 Prossimi Passi

1. **Testa con lo script:** Visita `test-en-subfolder.php` per verificare tutto
2. **Flush rewrite rules:** Se necessario, vai su Impostazioni > Permalink e salva
3. **Verifica le pagine tradotte:** Clicca sui link delle pagine inglesi per assicurarti che funzionino
4. **Rimuovi lo script di test:** Una volta verificato, puoi eliminare `test-en-subfolder.php` (opzionale)

## 📝 Note Tecniche

### Come Funziona

1. **Creazione Pagina Tradotta:**
   - La pagina viene creata con slug `en-nome-pagina`
   - Il metafield `_fpml_is_translation` viene impostato a `1`
   - Viene collegata alla pagina italiana originale

2. **Generazione Permalink:**
   - WordPress chiama `get_permalink($post_id)`
   - Il filtro `filter_translation_permalink` intercetta la chiamata
   - Se la pagina è una traduzione e ha slug `en-*`, il filtro:
     - Rimuove il prefisso `en-` dallo slug
     - Aggiunge `/en/` all'inizio del path
     - Gestisce la gerarchia parent se presente
     - Restituisce l'URL corretto

3. **Routing:**
   - Le rewrite rules di WordPress intercettano `/en/*`
   - La regola rewrite è: `^en/(.+)/?$` → `index.php?fpml_lang=en&fpml_path=$matches[1]`
   - Il sistema FPML_Rewrites mappa il path alla pagina corretta

### Compatibilità

- ✅ WordPress 5.0+
- ✅ Multisite
- ✅ Permalink personalizzati
- ✅ Pagine gerarchiche
- ✅ Custom Post Types

## 🐛 Segnalazione Bug

Se riscontri problemi:

1. Esegui lo script di test e fai uno screenshot
2. Controlla i log di WordPress (wp-content/debug.log)
3. Verifica le rewrite rules: `var_dump(get_option('rewrite_rules'));`
4. Segnala il problema con le informazioni raccolte
