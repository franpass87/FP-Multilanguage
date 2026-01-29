# Preview Translation Endpoint

**Versione**: 0.4.1  
**Endpoint**: `POST /wp-json/fpml/v1/preview-translation`  
**Autenticazione**: Richiesta (Admin + WordPress Nonce)

---

## Descrizione

Endpoint REST per ottenere un'anteprima di una traduzione senza salvarla nel database. Utile per:
- Testare qualità traduzioni prima di applicarle
- Confrontare output di provider diversi
- Stimare costi API
- Validare traduzioni in tempo reale

---

## Autenticazione

Richiede:
- **Capabilities**: `manage_options` (admin WordPress)
- **Nonce**: WordPress REST API nonce valido

### Esempio Header
```http
POST /wp-json/fpml/v1/preview-translation HTTP/1.1
Host: example.com
Content-Type: application/json
X-WP-Nonce: abc123def456
```

---

## Parametri Request

| Parametro | Tipo | Richiesto | Default | Descrizione |
|-----------|------|-----------|---------|-------------|
| `text` | string | ✅ Sì | - | Testo da tradurre |
| `provider` | string | ❌ No | Configurato | Provider da usare: `openai`, `deepl`, `google`, `libretranslate` |
| `source` | string | ❌ No | `it` | Lingua sorgente (codice ISO 639-1) |
| `target` | string | ❌ No | `en` | Lingua target (codice ISO 639-1) |

### Esempio Request Body
```json
{
  "text": "Benvenuto nel nostro negozio online",
  "provider": "openai",
  "source": "it",
  "target": "en"
}
```

---

## Risposta

### Successo (200 OK)

```json
{
  "success": true,
  "original": "Benvenuto nel nostro negozio online",
  "translated": "Welcome to our online store",
  "provider": "openai",
  "cached": false,
  "elapsed": 1.2456,
  "characters": 35,
  "estimated_cost": 0.00007
}
```

#### Campi Risposta

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `success` | boolean | `true` se traduzione riuscita |
| `original` | string | Testo originale |
| `translated` | string | Testo tradotto (sanitizzato con `wp_kses_post`) |
| `provider` | string | Provider usato |
| `cached` | boolean | `true` se risultato dalla cache |
| `elapsed` | float | Tempo di elaborazione in secondi (solo se non cached) |
| `characters` | integer | Numero di caratteri del testo originale |
| `estimated_cost` | float | Costo stimato in EUR |

### Risposta Cached

Se la traduzione è presente in cache:

```json
{
  "success": true,
  "original": "Benvenuto nel nostro negozio online",
  "translated": "Welcome to our online store",
  "provider": "openai",
  "cached": true,
  "characters": 35,
  "estimated_cost": 0
}
```

**Note**: 
- `elapsed` non è presente (istantaneo dalla cache)
- `estimated_cost` è 0 (nessuna chiamata API)

---

## Errori

### 400 Bad Request

**Testo mancante**:
```json
{
  "code": "fpml_empty_text",
  "message": "Testo da tradurre mancante.",
  "data": {
    "status": 400
  }
}
```

**API Key mancante**:
```json
{
  "code": "fpml_no_api_key",
  "message": "API key OpenAI mancante.",
  "data": {
    "status": 400
  }
}
```

**Provider non valido**:
```json
{
  "code": "fpml_invalid_provider",
  "message": "Provider non valido.",
  "data": {
    "status": 400
  }
}
```

**Errore traduzione**:
```json
{
  "code": "fpml_translation_error",
  "message": "OpenAI API error: Rate limit exceeded",
  "data": {
    "status": 400
  }
}
```

### 403 Forbidden

**Permessi insufficienti**:
```json
{
  "code": "fpml_rest_forbidden",
  "message": "Permessi insufficienti.",
  "data": {
    "status": 403
  }
}
```

**Nonce non valido**:
```json
{
  "code": "fpml_rest_nonce_invalid",
  "message": "Nonce non valido.",
  "data": {
    "status": 403
  }
}
```

### 429 Too Many Requests

**Rate limit ecceduto**:
```json
{
  "code": "fpml_rate_limit",
  "message": "Rate limit exceeded for openai. Retry after 45 seconds.",
  "data": {
    "status": 429
  }
}
```

---

## Esempi di Utilizzo

### JavaScript (Admin WordPress)

```javascript
// Funzione helper per preview traduzioni
async function previewTranslation(text, provider = null) {
    const response = await fetch('/wp-json/fpml/v1/preview-translation', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({
            text: text,
            provider: provider, // null = usa provider configurato
            source: 'it',
            target: 'en'
        })
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message);
    }

    return await response.json();
}

// Uso
try {
    const result = await previewTranslation('Ciao mondo!');
    console.log('Tradotto:', result.translated);
    console.log('Costo:', result.estimated_cost);
    console.log('Dalla cache:', result.cached);
} catch (error) {
    console.error('Errore:', error.message);
}
```

### jQuery

```javascript
jQuery(document).ready(function($) {
    $('#preview-button').on('click', function() {
        var text = $('#text-to-translate').val();
        
        $.ajax({
            url: '/wp-json/fpml/v1/preview-translation',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            data: JSON.stringify({
                text: text,
                provider: 'openai'
            }),
            contentType: 'application/json',
            success: function(response) {
                $('#translated-text').val(response.translated);
                $('#cost-estimate').text('$' + response.estimated_cost);
            },
            error: function(xhr) {
                var error = xhr.responseJSON;
                alert('Errore: ' + error.message);
            }
        });
    });
});
```

### PHP (WP-CLI o plugin esterno)

```php
// Richiesta con wp_remote_post
$response = wp_remote_post(
    rest_url('fpml/v1/preview-translation'),
    array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-WP-Nonce'   => wp_create_nonce('wp_rest'),
        ),
        'body' => wp_json_encode(array(
            'text'     => 'Testo da tradurre',
            'provider' => 'deepl',
            'source'   => 'it',
            'target'   => 'en',
        )),
    )
);

if (is_wp_error($response)) {
    echo 'Errore: ' . $response->get_error_message();
} else {
    $data = json_decode(wp_remote_retrieve_body($response), true);
    echo 'Traduzione: ' . $data['translated'];
}
```

### cURL

```bash
# Con nonce (ottenuto da admin WordPress)
curl -X POST https://example.com/wp-json/fpml/v1/preview-translation \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: abc123def456" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "text": "Ciao mondo!",
    "provider": "openai",
    "source": "it",
    "target": "en"
  }'
```

---

## Casi d'Uso

### 1. Confronto Provider

Testa qualità traduzioni tra provider diversi:

```javascript
async function compareProviders(text) {
    const providers = ['openai', 'deepl', 'google'];
    const results = {};
    
    for (const provider of providers) {
        try {
            const result = await previewTranslation(text, provider);
            results[provider] = {
                translation: result.translated,
                cost: result.estimated_cost,
                time: result.elapsed,
                cached: result.cached
            };
        } catch (error) {
            results[provider] = { error: error.message };
        }
    }
    
    return results;
}

// Uso
const comparison = await compareProviders('Benvenuto');
console.table(comparison);
```

### 2. Validazione Batch

Valida traduzioni prima di applicarle in batch:

```javascript
async function validateBatchTranslations(texts) {
    const validations = [];
    
    for (const text of texts) {
        const result = await previewTranslation(text);
        validations.push({
            original: text,
            translated: result.translated,
            cost: result.estimated_cost,
            needsReview: text.length > 500 // Flag testi lunghi
        });
    }
    
    // Mostra riepilogo
    const totalCost = validations.reduce((sum, v) => sum + v.cost, 0);
    console.log(`Total cost: $${totalCost}`);
    console.log(`Items needing review: ${validations.filter(v => v.needsReview).length}`);
    
    return validations;
}
```

### 3. Preview in Editor

Integrazione con Gutenberg o editor classico:

```javascript
// Blocco Gutenberg custom
const { Button } = wp.components;
const { useState } = wp.element;

function TranslationPreview({ text }) {
    const [preview, setPreview] = useState(null);
    const [loading, setLoading] = useState(false);

    const handlePreview = async () => {
        setLoading(true);
        try {
            const result = await previewTranslation(text);
            setPreview(result.translated);
        } catch (error) {
            alert(error.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <Button 
                isPrimary 
                onClick={handlePreview}
                isBusy={loading}
            >
                Preview Traduzione
            </Button>
            {preview && (
                <div className="preview-box">
                    <strong>Anteprima:</strong>
                    <p>{preview}</p>
                </div>
            )}
        </div>
    );
}
```

---

## Performance e Caching

### Strategia di Cache

L'endpoint utilizza una cache a doppio livello:

1. **Object Cache** (Redis/Memcached se disponibile)
   - Velocità: < 1ms
   - TTL: 24 ore (filtrabile via `fpml_cache_ttl`)

2. **Transient Cache** (Database)
   - Fallback se object cache non disponibile
   - TTL: 24 ore

### Invalidazione Cache

La cache viene invalidata automaticamente quando:
- Cambiano le impostazioni del provider
- Viene modificato il glossario
- Viene eseguito un cleanup manuale

### Ottimizzazioni

Per massimizzare le performance:

```php
// Aumenta TTL cache per testi ricorrenti
add_filter('fpml_cache_ttl', function($ttl) {
    return WEEK_IN_SECONDS; // 7 giorni invece di 1
});

// Pre-warm cache per contenuti popolari
add_action('fpml_preview_cache_warm', function() {
    $common_phrases = [
        'Aggiungi al carrello',
        'Scopri di più',
        'Contattaci',
        // ...
    ];
    
    foreach ($common_phrases as $phrase) {
        // Trigger preview per popolare cache
        do_preview_translation($phrase);
    }
});
```

---

## Limiti e Considerazioni

### Rate Limiting

- **Admin**: Nessun limite (fidato)
- **Provider API**: Rispetta i limiti del provider configurato
- **Cache**: Hit rate target 70%+

### Lunghezza Testo

- **Minimo**: 1 carattere
- **Massimo**: Dipende dal provider
  - OpenAI: ~128000 caratteri (gpt-5-nano)
  - DeepL: ~5000 caratteri
  - Google: ~5000 caratteri
  - LibreTranslate: ~1000 caratteri

Per testi più lunghi, considera di dividerli in chunk.

### Costi

Preview **consuma crediti API** se non in cache. Monitora l'uso:

```javascript
// Traccia costi preview
let totalPreviewCost = 0;

async function trackedPreview(text) {
    const result = await previewTranslation(text);
    
    if (!result.cached) {
        totalPreviewCost += result.estimated_cost;
        console.log(`Preview cost: $${result.estimated_cost}`);
        console.log(`Total preview costs: $${totalPreviewCost}`);
    }
    
    return result;
}
```

---

## Sicurezza

### Protezioni Implementate

- ✅ **Capability check**: Solo admin
- ✅ **Nonce validation**: Protezione CSRF
- ✅ **Input sanitization**: `sanitize_textarea_field`
- ✅ **Output sanitization**: `wp_kses_post`
- ✅ **Rate limiting**: Via provider

### Best Practices

```javascript
// ✅ CORRETTO - Usa nonce WordPress
fetch('/wp-json/fpml/v1/preview-translation', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});

// ❌ SBAGLIATO - Nessuna autenticazione
fetch('/wp-json/fpml/v1/preview-translation');

// ✅ CORRETTO - Sanitizza output
const result = await previewTranslation(userInput);
element.textContent = result.translated; // Safe

// ❌ SBAGLIATO - XSS risk
element.innerHTML = result.translated; // Dangerous!
```

---

## Debugging

### Abilitare Debug

```php
// wp-config.php
define('FPML_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Log Richieste

```javascript
// Wrapper con logging
async function debugPreview(text, provider) {
    console.log('Preview request:', { text, provider });
    
    try {
        const result = await previewTranslation(text, provider);
        console.log('Preview response:', result);
        return result;
    } catch (error) {
        console.error('Preview error:', error);
        throw error;
    }
}
```

### Verificare Cache

```php
// Verifica se traduzione è in cache
$cache = FPML_Container::get('translation_cache');
$cached = $cache->get($text, $provider, 'it', 'en');

if (false !== $cached) {
    echo "In cache: $cached\n";
} else {
    echo "Non in cache, richiederà API call\n";
}
```

---

## Changelog

### v0.4.1 (2025-10-08)
- ✨ Primo rilascio endpoint preview
- ✨ Supporto multi-provider
- ✨ Integrazione cache
- ✨ Stima costi real-time

---

## Supporto

- **Issues**: [GitHub Issues](https://github.com/francescopasseri/FP-Multilanguage/issues)
- **Docs**: `/docs` directory
- **Email**: info@francescopasseri.com

---

*Documento aggiornato il 2025-10-08*
