# Webhook Notifications Guide - FP Multilanguage

## Overview

Il plugin può inviare notifiche webhook quando eventi importanti accadono (batch completati, cleanup eseguiti, etc.). Questo ti permette di integrare con Slack, Discord, Teams, o qualsiasi servizio che accetti webhook.

---

## Setup

### 1. Configurazione Base

Aggiungi webhook URL nelle impostazioni plugin:

```php
// Via Settings UI (prossima versione)
// O via wp-config.php:
define( 'FPML_WEBHOOK_URL', 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL' );
```

**O via database:**
```bash
wp option update fpml_settings '{"webhook_url":"https://your-webhook-url.com"}' --format=json
```

---

## Integrazioni Comuni

### Slack

#### Step 1: Crea Incoming Webhook in Slack
1. Vai su https://api.slack.com/apps
2. Crea nuova app o seleziona esistente
3. Vai su "Incoming Webhooks"
4. Attiva e crea nuovo webhook
5. Copia webhook URL (es: `https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXX`)

#### Step 2: Configura nel Plugin
```bash
wp option patch insert fpml_settings webhook_url "https://hooks.slack.com/services/YOUR/WEBHOOK/URL"
```

#### Step 3: Test
```bash
wp eval "FPML_Webhooks::instance()->test_webhook();"
```

#### Esempio Payload Ricevuto da Slack:
```json
{
  "event": "batch.complete",
  "summary": {
    "claimed": 25,
    "processed": 24,
    "skipped": 0,
    "errors": 1
  },
  "timestamp": "2025-10-05 14:30:00",
  "site_url": "https://example.com",
  "site_name": "My WordPress Site"
}
```

#### Formattazione Slack (opzionale)

Crea middleware per formattare messaggi Slack:
```php
// In functions.php del tema o custom plugin
add_filter( 'fpml_webhook_payload', function( $payload, $url ) {
    // Check if Slack URL
    if ( strpos( $url, 'hooks.slack.com' ) === false ) {
        return $payload;
    }
    
    // Format for Slack
    if ( $payload['event'] === 'batch.complete' ) {
        $summary = $payload['summary'];
        return array(
            'text' => sprintf(
                '🔄 *Batch Traduzioni Completato*\n' .
                '✅ Processati: %d\n' .
                '⏭️ Saltati: %d\n' .
                '❌ Errori: %d\n' .
                '🕐 %s',
                $summary['processed'],
                $summary['skipped'],
                $summary['errors'],
                $payload['timestamp']
            ),
        );
    }
    
    return $payload;
}, 10, 2 );
```

---

### Discord

#### Step 1: Crea Webhook in Discord
1. Vai nelle impostazioni canale
2. Integrazioni → Webhook
3. Crea webhook
4. Copia URL (es: `https://discord.com/api/webhooks/123456789/abcdefgh`)

#### Step 2: Configura
```bash
wp option patch insert fpml_settings webhook_url "https://discord.com/api/webhooks/YOUR_ID/YOUR_TOKEN"
```

#### Formattazione Discord:
```php
add_filter( 'fpml_webhook_payload', function( $payload, $url ) {
    if ( strpos( $url, 'discord.com/api/webhooks' ) === false ) {
        return $payload;
    }
    
    if ( $payload['event'] === 'batch.complete' ) {
        $summary = $payload['summary'];
        return array(
            'content' => sprintf(
                '🔄 **Batch Traduzioni Completato**\n' .
                '✅ Processati: %d | ⏭️ Saltati: %d | ❌ Errori: %d',
                $summary['processed'],
                $summary['skipped'],
                $summary['errors']
            ),
            'username' => 'FP Multilanguage',
        );
    }
    
    return $payload;
}, 10, 2 );
```

---

### Microsoft Teams

#### Step 1: Crea Incoming Webhook
1. Vai nel canale Teams
2. Connettori → Incoming Webhook
3. Configura e ottieni URL

#### Step 2: Formatta per Teams
```php
add_filter( 'fpml_webhook_payload', function( $payload, $url ) {
    if ( strpos( $url, 'webhook.office.com' ) === false ) {
        return $payload;
    }
    
    if ( $payload['event'] === 'batch.complete' ) {
        $summary = $payload['summary'];
        return array(
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => 'Translation Batch Complete',
            'themeColor' => '0078D7',
            'title' => '🔄 Batch Traduzioni Completato',
            'sections' => array(
                array(
                    'facts' => array(
                        array( 'name' => 'Processati', 'value' => $summary['processed'] ),
                        array( 'name' => 'Saltati', 'value' => $summary['skipped'] ),
                        array( 'name' => 'Errori', 'value' => $summary['errors'] ),
                    ),
                ),
            ),
        );
    }
    
    return $payload;
}, 10, 2 );
```

---

### Webhook Custom (API Personalizzata)

Se hai un tuo endpoint:

```php
// Il tuo server riceverà:
POST /your-webhook-endpoint
Content-Type: application/json
User-Agent: FP-Multilanguage/0.3.2

{
  "event": "batch.complete",
  "summary": {
    "claimed": 25,
    "processed": 24,
    "skipped": 0,
    "errors": 1
  },
  "timestamp": "2025-10-05 14:30:00",
  "site_url": "https://example.com",
  "site_name": "My Site"
}
```

**Esempio handler Node.js/Express:**
```javascript
app.post('/fpml-webhook', (req, res) => {
    const { event, summary, site_name } = req.body;
    
    if (event === 'batch.complete') {
        console.log(`${site_name}: Processed ${summary.processed} translations`);
        
        // Invia notifica email, update dashboard, etc.
    }
    
    res.sendStatus(200);
});
```

---

## Eventi Disponibili

### `batch.complete`
Inviato quando un batch di traduzioni completa.

**Payload:**
```json
{
  "event": "batch.complete",
  "summary": {
    "claimed": 20,
    "processed": 18,
    "skipped": 1,
    "errors": 1
  },
  "timestamp": "2025-10-05 14:30:00",
  "site_url": "https://example.com",
  "site_name": "My WordPress Site"
}
```

**Quando:** Dopo ogni esecuzione `wp fpml queue run` o cron batch

---

### `cleanup.complete`
Inviato quando cleanup della coda completa.

**Payload:**
```json
{
  "event": "cleanup.complete",
  "deleted": 532,
  "states": ["done", "skipped"],
  "days": 7,
  "timestamp": "2025-10-05 03:00:00",
  "site_url": "https://example.com",
  "site_name": "My WordPress Site"
}
```

**Quando:** Dopo cleanup manuale o automatico

---

## Testing

### Test Configurazione
```bash
# Via WP-CLI
wp eval "
\$webhook = FPML_Webhooks::instance();
\$result = \$webhook->test_webhook();
if ( is_wp_error( \$result ) ) {
    echo 'Error: ' . \$result->get_error_message();
} else {
    echo 'Success! Webhook configurato correttamente.';
}
"
```

### Test Manuale
```bash
# Trigger batch complete webhook manualmente
wp eval "
do_action( 'fpml_queue_batch_complete', array(
    'claimed' => 10,
    'processed' => 9,
    'skipped' => 0,
    'errors' => 1
));
"
```

---

## Troubleshooting

### Webhook non arrivano

**1. Verifica URL configurato:**
```bash
wp option get fpml_settings --format=json | grep webhook_url
```

**2. Check logs:**
```bash
wp eval "
\$logs = FPML_Logger::instance()->get_logs_by_event('webhook.error', 10);
print_r(\$logs);
"
```

**3. Test connectivity:**
```bash
# Test che WordPress possa raggiungere l'URL
wp eval "
\$response = wp_remote_post('YOUR_WEBHOOK_URL', array(
    'body' => json_encode(array('test' => true)),
    'headers' => array('Content-Type' => 'application/json')
));
print_r(\$response);
"
```

---

### Webhook ricevuti ma formato sbagliato

Usa il filter `fpml_webhook_payload`:
```php
add_filter( 'fpml_webhook_payload', function( $payload, $url ) {
    // Debug
    error_log( 'Webhook payload: ' . print_r( $payload, true ) );
    
    // Trasforma formato
    return array(
        'message' => 'Custom format',
        'data' => $payload,
    );
}, 10, 2 );
```

---

## Security

### Autenticazione Webhook

Se il tuo endpoint richiede autenticazione:

```php
add_filter( 'http_request_args', function( $args, $url ) {
    // Check if it's our webhook
    $webhook_url = FPML_Settings::instance()->get( 'webhook_url' );
    
    if ( $url !== $webhook_url ) {
        return $args;
    }
    
    // Add authentication header
    if ( ! isset( $args['headers'] ) ) {
        $args['headers'] = array();
    }
    
    $args['headers']['Authorization'] = 'Bearer YOUR_SECRET_TOKEN';
    
    return $args;
}, 10, 2 );
```

### Validazione Lato Server

Sul tuo webhook endpoint:
```javascript
app.post('/webhook', (req, res) => {
    const authHeader = req.headers['authorization'];
    
    if (authHeader !== 'Bearer YOUR_SECRET_TOKEN') {
        return res.sendStatus(401);
    }
    
    // Process webhook
    const { event, summary } = req.body;
    
    res.sendStatus(200);
});
```

---

## Best Practices

### 1. Timeout Breve
I webhook hanno timeout di 5 secondi - assicurati che il tuo endpoint risponda rapidamente.

### 2. Retry Logic
Il plugin NON fa retry automatico. Se il webhook fallisce, viene loggato ma non ritentato.

### 3. Async Processing
Se devi fare elaborazioni lunghe, metti in queue:
```javascript
app.post('/webhook', async (req, res) => {
    // Rispondi subito
    res.sendStatus(200);
    
    // Process async
    await queue.add('process-translation-webhook', req.body);
});
```

### 4. Rate Limiting
Se ricevi troppi webhook, implementa throttling lato server.

---

## Examples

### Zapier Integration

1. Create Zapier webhook trigger
2. Usa URL webhook Zapier in plugin
3. Setup azioni (email, Google Sheets, etc.)

### n8n Integration

```json
{
  "nodes": [
    {
      "name": "Webhook",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 1,
      "position": [250, 300],
      "webhookId": "fpml-notifications"
    },
    {
      "name": "Send Email",
      "type": "n8n-nodes-base.emailSend",
      "typeVersion": 1,
      "position": [450, 300]
    }
  ]
}
```

### Make (Integromat)

1. Create new scenario
2. Add webhook module
3. Copy webhook URL
4. Configure plugin con URL
5. Setup automazioni

---

**Last updated:** 2025-10-05  
**Plugin version:** 0.3.2
