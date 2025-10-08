# Guida Migrazione - Refactoring Modularizzazione

## Per Sviluppatori che Estendono il Plugin

Se hai estensioni o personalizzazioni che usano il codice di FP Multilanguage, ecco cosa devi sapere:

---

## ✅ Codice che Continua a Funzionare (100% BC)

Tutto il codice esistente **continua a funzionare senza modifiche**:

```php
// ✅ FUNZIONA - Singleton principale
$plugin = FPML_Plugin::instance();

// ✅ FUNZIONA - Tutti i metodi pubblici
$plugin->reindex_content();
$plugin->get_diagnostics_snapshot();
$plugin->estimate_queue_cost();
$plugin->is_assisted_mode();

// ✅ FUNZIONA - Hook esistenti
add_action('fpml_post_jobs_enqueued', 'my_custom_callback');
add_filter('fpml_translatable_post_types', 'my_filter');
```

---

## 🎯 Codice Consigliato (Nuovo Approccio)

Per nuovo codice, usa il **Service Container** per accesso più pulito:

### Esempio 1: Reindexing

**Prima**:
```php
$plugin = FPML_Plugin::instance();
$summary = $plugin->reindex_content();
```

**Ora (consigliato)**:
```php
$indexer = FPML_Container::get('content_indexer');
$summary = $indexer->reindex_content();
```

### Esempio 2: Creare una Traduzione

**Prima**:
```php
// Dovevi chiamare metodi protected o duplicare logica
```

**Ora (consigliato)**:
```php
$translation_manager = FPML_Container::get('translation_manager');
$target_post = $translation_manager->ensure_post_translation($source_post);
```

### Esempio 3: Accodare Job di Traduzione

**Prima**:
```php
// Non c'era API pubblica diretta
```

**Ora (consigliato)**:
```php
$job_enqueuer = FPML_Container::get('job_enqueuer');
$job_enqueuer->enqueue_post_jobs($source_post, $target_post, true);
```

### Esempio 4: Diagnostica

**Prima**:
```php
$plugin = FPML_Plugin::instance();
$snapshot = $plugin->get_diagnostics_snapshot();
```

**Ora (consigliato)**:
```php
$diagnostics = FPML_Container::get('diagnostics');
$snapshot = $diagnostics->get_snapshot(false, '');
```

---

## 📋 Servizi Disponibili nel Container

| Nome Servizio | Classe | Descrizione |
|--------------|--------|-------------|
| `settings` | `FPML_Settings` | Gestione impostazioni plugin |
| `logger` | `FPML_Logger` | Sistema di logging |
| `queue` | `FPML_Queue` | Gestione coda traduzioni |
| `translation_manager` | `FPML_Translation_Manager` | Creazione traduzioni post/term |
| `job_enqueuer` | `FPML_Job_Enqueuer` | Accodamento lavori |
| `diagnostics` | `FPML_Diagnostics` | Metriche e diagnostica |
| `cost_estimator` | `FPML_Cost_Estimator` | Stima costi traduzioni |
| `content_indexer` | `FPML_Content_Indexer` | Reindexing contenuti |

### Come Usare il Container

```php
// Ottenere un servizio
$service = FPML_Container::get('nome_servizio');

// Verificare se esiste
if (FPML_Container::has('nome_servizio')) {
    // ...
}

// In test: resettare per isolamento
FPML_Container::clear_all();
```

---

## 🔧 Testing delle Estensioni

### Se hai creato un plugin che estende FP Multilanguage:

1. **Testa con il nuovo codice**
   - Verifica che le chiamate a `FPML_Plugin::instance()` funzionino
   - Controlla che i tuoi hook/filtri siano ancora chiamati
   - Assicurati che le integrazioni funzionino

2. **Considera di migrare** (opzionale ma consigliato)
   - Usa `FPML_Container::get()` per dipendenze esplicite
   - Accedi direttamente ai servizi specializzati
   - Evita di chiamare metodi interni (protected/private)

3. **Benefici della migrazione**
   - Codice più testabile
   - Dipendenze esplicite
   - Più resistente a cambiamenti futuri

---

## ⚠️ Deprecazioni (Soft)

Le seguenti proprietà/metodi sono tecnicamente deprecati ma **continuano a funzionare**:

```php
// ⚠️ Deprecato (ma funziona ancora)
$plugin = FPML_Plugin::instance();
$creating = $plugin->creating_translation;

// ✅ Alternativa moderna
$translation_manager = FPML_Container::get('translation_manager');
$creating = $translation_manager->is_creating_translation();
```

**Non ci saranno breaking changes** fino a una eventuale versione 1.0.0 (major).

---

## 🐛 Troubleshooting

### Problema: "Class FPML_Container not found"

**Soluzione**: Assicurati che il plugin sia completamente attivato:
```bash
wp plugin deactivate fp-multilanguage
wp plugin activate fp-multilanguage
```

### Problema: "Call to undefined method"

**Causa**: Stai chiamando un metodo che era protected nella vecchia versione.

**Soluzione**: Usa il servizio specializzato appropriato dal Container.

### Problema: Test falliscono

**Soluzione**: Resetta il container prima di ogni test:
```php
protected function setUp(): void {
    parent::setUp();
    FPML_Container::clear_all();
}
```

---

## 📚 Esempi Completi

### Creare una Traduzione Personalizzata

```php
function my_custom_create_translation($post_id) {
    $post = get_post($post_id);
    
    if (!$post) {
        return false;
    }
    
    // Usa il servizio Translation Manager
    $translation_manager = FPML_Container::get('translation_manager');
    $target_post = $translation_manager->ensure_post_translation($post);
    
    if ($target_post) {
        // Accoda i job di traduzione
        $job_enqueuer = FPML_Container::get('job_enqueuer');
        $job_enqueuer->enqueue_post_jobs($post, $target_post, false);
        
        return $target_post->ID;
    }
    
    return false;
}
```

### Hook Personalizzato dopo Traduzione

```php
add_action('fpml_post_jobs_enqueued', function($source_post, $target_post, $update) {
    // Logica personalizzata
    if ('product' === $source_post->post_type) {
        // Sincronizza dati extra per WooCommerce
        sync_woocommerce_data($source_post->ID, $target_post->ID);
    }
}, 10, 3);
```

### Stima Costi Prima di Reindex

```php
function estimate_before_reindex() {
    $cost_estimator = FPML_Container::get('cost_estimator');
    
    $estimate = $cost_estimator->estimate(['pending', 'outdated'], 1000);
    
    if (is_wp_error($estimate)) {
        return;
    }
    
    echo sprintf(
        'Costo stimato: €%.2f per %d caratteri (%d parole)',
        $estimate['estimated_cost'],
        $estimate['characters'],
        $estimate['word_count']
    );
}
```

---

## 🎓 Best Practices

### ✅ DO

- Usa `FPML_Container::get()` per ottenere servizi
- Mantieni le dipendenze esplicite
- Testa con il nuovo codice ma mantieni BC
- Documenta quale servizio usi

### ❌ DON'T

- Non accedere a proprietà protected/private di classi
- Non duplicare logica che ora è in servizi dedicati
- Non assumere che la struttura interna non cambierà
- Non bypassare il Container per creare istanze

---

## 📞 Supporto

- **Documentazione**: Vedi `REFACTORING_COMPLETATO.md`
- **Esempi**: Vedi `docs/examples/`
- **Issue**: Apri una issue su GitHub
- **Breaking Changes**: Saranno comunicati con largo anticipo

---

**Versione**: 0.4.0  
**Data**: 2025-10-08  
**Status**: ✅ Backward Compatible
