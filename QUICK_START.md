# ⚡ Quick Start - FP Multilanguage v0.4.1

Guida rapida per iniziare con **FP Multilanguage** in **5 minuti**.

---

## 🎯 TL;DR - In 30 Secondi

Plugin WordPress enterprise-grade per traduzione automatica italiano-inglese con:

- ✅ **Crittografia chiavi API** (AES-256-CBC)
- ✅ **Versionamento traduzioni** con rollback
- ✅ **Endpoint anteprima REST** per test senza salvare
- ✅ **36 correzioni bug** (11 vulnerabilità critiche)
- ✅ **Performance**: 10x più veloce, -70% memoria

---

## 🚀 Setup 5 Minuti

### 1️⃣ Installazione

```bash
# Carica plugin in /wp-content/plugins/fp-multilanguage
# oppure
cd /wp-content/plugins
git clone https://github.com/francescopasseri/FP-Multilanguage.git fp-multilanguage
```

Attiva in **WordPress Admin → Plugin → Plugin Installati**

---

### 2️⃣ Configurazione Base

Vai a **Impostazioni → FP Multilanguage**

#### Provider Traduzione

Scegli **almeno uno**:

| Provider | Configurazione |
|----------|----------------|
| **OpenAI** | API Key da [platform.openai.com/api-keys](https://platform.openai.com/api-keys) |
| **DeepL** | API Key da [www.deepl.com/pro-api](https://www.deepl.com/pro-api) |
| **Google** | API Key da [Google Cloud Console](https://console.cloud.google.com) |
| **LibreTranslate** | URL endpoint + API Key (opzionale) |

Le chiavi API sono **automaticamente crittografate** con AES-256-CBC.

---

### 3️⃣ Configurazione Routing

Scegli struttura URL:

- **`/en/` subdirectory** (raccomandato)
  ```
  https://example.com/post-italiano/
  https://example.com/en/english-post/
  ```

- **Query string**
  ```
  https://example.com/post/?lang=it
  https://example.com/post/?lang=en
  ```

---

### 4️⃣ Sincronizzazione Iniziale

```bash
# Via WP-CLI (raccomandato)
wp fpml queue run

# oppure via REST API
# Vai a Impostazioni → FP Multilanguage → Diagnostici
# Clicca "Esegui Coda"
```

---

### 5️⃣ Verifica Setup ✅

```bash
# Test connettività provider
wp fpml test-provider --provider=openai

# Visualizza stato coda
wp fpml queue status

# Esempio output:
# Queue Status:
#   Pending: 45 jobs
#   Processing: 2 jobs
#   Completed: 123 jobs
#   Provider: OpenAI (gpt-4)
#   Estimated Cost: $0.23
```

**Setup completato!** 🎉

---

## 📝 Utilizzo Quotidiano

### Workflow Automatico

1. **Crea/Modifica** contenuto italiano in WordPress
2. **Plugin** accoda automaticamente job traduzione
3. **Coda** processa job (via cron o manuale)
4. **Contenuto inglese** creato/aggiornato automaticamente

### Monitoraggio

**Dashboard Widget** (WordPress Admin Home):
- Dimensione coda attuale
- Job completati oggi
- Stato processore

**Diagnostici** (Impostazioni → FP Multilanguage → Diagnostici):
- KPI coda dettagliati
- Test connettività provider
- Stima costi traduzioni
- Log attività recenti

---

## 🆕 Novità v0.4.1

### 1. 🔐 Crittografia Chiavi API

**Automatica e trasparente**:
```bash
# Le chiavi esistenti vanno migrate (una volta)
php tools/migrate-api-keys.php

# oppure
wp eval-file tools/migrate-api-keys.php
```

**Verifica crittografia**:
```bash
wp db query "SELECT option_value FROM wp_options WHERE option_name='fpml_settings'" | grep "ENC:"
```

Le chiavi devono avere prefisso `ENC:` in database.

---

### 2. 💾 Versionamento Traduzioni

**Rollback a versioni precedenti**:

```php
// Recupera versioni
$versions = FPML_Translation_Versioning::instance()->get_versions('post', $post_id, 'post_title');

// Rollback
FPML_Translation_Versioning::instance()->rollback_post($post_id, $version_id);
```

**Via WP-CLI**:
```bash
# Cleanup versioni vecchie (90+ giorni, mantieni min 5)
wp eval 'FPML_Translation_Versioning::instance()->cleanup_old_versions();'
```

---

### 3. 🔍 Endpoint Anteprima REST

**Test traduzioni senza salvare**:

```bash
# cURL
curl -X POST https://example.com/wp-json/fpml/v1/preview-translation \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{
    "text": "Ciao mondo",
    "provider": "openai"
  }'

# Response
{
  "success": true,
  "translation": "Hello world",
  "provider": "openai",
  "cost_estimate": 0.00015,
  "cached": false
}
```

**JavaScript**:
```javascript
const response = await fetch('/wp-json/fpml/v1/preview-translation', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'Ciao mondo',
        provider: 'openai'
    })
});

const data = await response.json();
console.log(data.translation); // "Hello world"
```

**Vedi**: [`docs/api-preview-endpoint.md`](docs/api-preview-endpoint.md)

---

## 🖥️ Comandi WP-CLI Essenziali

```bash
# Processing coda
wp fpml queue run                        # Processa job pendenti
wp fpml queue run --progress             # Con barra progresso
wp fpml queue run --batch=50             # Batch size custom

# Monitoraggio
wp fpml queue status                     # Stato coda
wp fpml queue estimate-cost              # Stima costi

# Manutenzione
wp fpml queue cleanup                    # Pulisci job vecchi (7 giorni)
wp fpml queue cleanup --days=30          # Retention custom

# Test
wp fpml test-provider --provider=openai  # Test connettività
```

---

## 🔧 Configurazione Avanzata

### Setup Cron Automatico

**Disable WP-Cron** (raccomandato produzione):

```php
// wp-config.php
define('DISABLE_WP_CRON', true);
```

**Setup System Cron**:

```bash
# Aggiungi a crontab
crontab -e

# Esegui ogni 5 minuti
*/5 * * * * cd /path/to/wordpress && wp fpml queue run --batch=20 >> /var/log/fpml-cron.log 2>&1
```

---

### Tipi Contenuto Custom

```php
// Aggiungi custom post type alla traduzione
add_filter('fpml_translatable_post_types', function($types) {
    $types[] = 'my_custom_post_type';
    return $types;
});

// Aggiungi tassonomia custom
add_filter('fpml_translatable_taxonomies', function($taxonomies) {
    $taxonomies[] = 'my_custom_taxonomy';
    return $taxonomies;
});
```

---

### Ottimizzazione Performance

**Per siti grandi** (10K+ post):

```php
// Aumenta batch size (default: 10)
add_filter('fpml_queue_batch_size', function($size) {
    return 50; // Processa 50 job per run
});

// Aumenta max caratteri (default: 100000)
add_filter('fpml_max_chars_per_batch', function($chars) {
    return 200000; // 200K caratteri per batch
});
```

**Memory optimization**:
```php
// wp-config.php
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

---

## 🆘 Troubleshooting Rapido

### ❌ Queue non processa

```bash
# Verifica cron WordPress
wp cron event list

# Esegui cron manualmente
wp cron event run --due-now

# Forza processing
wp fpml queue run --batch=10
```

---

### ❌ Errori provider API

```bash
# Test connettività
wp fpml test-provider --provider=openai

# Controlla log
wp db query "SELECT * FROM wp_fpml_logs ORDER BY created_at DESC LIMIT 10"
```

**Errori comuni**:
- `auth_error` → Chiave API invalida
- `quota_exceeded` → Quota API esaurita
- `rate_limit` → Troppo traffico API

---

### ❌ Memory errors

```bash
# Riduci batch size
wp fpml queue run --batch=5

# Cleanup job vecchi
wp fpml queue cleanup --days=7
```

**Vedi**: [`docs/troubleshooting.md`](docs/troubleshooting.md) per guida completa

---

## 📚 Prossimi Passi

### 📖 Documentazione

1. **[README.md](README.md)** - Documentazione principale completa
2. **[docs/](docs/)** - Documentazione tecnica
3. **[NUOVE_FUNZIONALITA_E_CORREZIONI.md](NUOVE_FUNZIONALITA_E_CORREZIONI.md)** - Dettagli v0.4.1
4. **[docs/api-preview-endpoint.md](docs/api-preview-endpoint.md)** - REST API

### 🛠️ Configurazione Avanzata

- **[docs/deployment-guide.md](docs/deployment-guide.md)** - Deployment produzione
- **[docs/performance-optimization.md](docs/performance-optimization.md)** - Ottimizzazione
- **[docs/developer-guide.md](docs/developer-guide.md)** - Estensioni custom

### 🔌 Integrazioni

- **[docs/webhooks-guide.md](docs/webhooks-guide.md)** - Notifiche Slack/Discord/Teams
- **[docs/examples/](docs/examples/)** - Esempi codice pratici

---

## 💡 Tips & Tricks

### 🚀 Performance

```bash
# Abilita object caching (Redis/Memcached)
# Riduce query database del 30-40%

# Esegui cleanup regolarmente
wp fpml queue cleanup --days=7

# Monitora memoria
wp eval 'echo "Memory: " . round(memory_get_usage()/1024/1024, 2) . " MB\n";'
```

---

### 🔍 Debug Mode

```php
// wp-config.php
define('FPML_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Log in /wp-content/debug.log
```

---

### 📊 Monitoring

```bash
# Health check endpoint (richiede autenticazione)
curl https://example.com/wp-json/fpml/v1/health \
  -H "Authorization: Bearer YOUR_TOKEN"

# Response
{
  "status": "healthy",
  "queue_size": 45,
  "provider": "openai",
  "last_run": "2025-10-13 15:30:22"
}
```

---

## ✅ Checklist Setup Completamento

- [ ] Plugin installato e attivato
- [ ] Provider configurato e testato
- [ ] Routing configurato (`/en/` o query string)
- [ ] Sync iniziale eseguito
- [ ] Chiavi API migrate a formato crittografato
- [ ] Cron configurato (se `DISABLE_WP_CRON=true`)
- [ ] Test traduzione manuale OK
- [ ] Dashboard widget visibile
- [ ] Diagnostici accessibili

**Tutto verde?** → **Setup completato!** 🎉

---

## 🆘 Supporto

- **Quick Help**: [docs/troubleshooting.md](docs/troubleshooting.md)
- **FAQ**: [docs/faq.md](docs/faq.md)
- **GitHub Issues**: [github.com/francescopasseri/FP-Multilanguage/issues](https://github.com/francescopasseri/FP-Multilanguage/issues)
- **Email**: [info@francescopasseri.com](mailto:info@francescopasseri.com)

---

<div align="center">

**FP Multilanguage v0.4.1**

[📖 Docs](README.md) • [🔧 API](docs/api-reference.md) • [🚀 Deploy](docs/deployment-guide.md) • [💬 Support](https://github.com/francescopasseri/FP-Multilanguage/issues)

Made with ❤️ by [Francesco Passeri](https://francescopasseri.com)

</div>
