# Sistema di Migrazione Impostazioni

## 🔧 Panoramica

Il sistema di migrazione delle impostazioni risolve il problema del reset delle configurazioni durante l'aggiornamento del plugin. Implementa un backup automatico e un ripristino intelligente delle impostazioni.

## 🚀 Come Funziona

### 1. Backup Automatico
- **Quando**: Prima di ogni attivazione/aggiornamento del plugin
- **Cosa**: Salva tutte le impostazioni correnti nel database
- **Dove**: Opzione `fpml_settings_backup` nel database WordPress

### 2. Ripristino Intelligente
- **Quando**: Durante l'inizializzazione del plugin
- **Logica**: 
  - Se le impostazioni sono vuote o ai valori predefiniti → Ripristina dal backup
  - Se le impostazioni sono configurate → Migra solo le nuove opzioni

### 3. Migrazione Nuove Opzioni
- Aggiunge automaticamente le nuove opzioni disponibili nelle versioni successive
- Preserva le impostazioni esistenti dell'utente
- Sanitizza tutte le impostazioni prima del salvataggio

## 📋 Impostazioni Monitorate

Il sistema monitora queste impostazioni critiche per determinare se è necessario un ripristino:

- `provider` - Provider di traduzione configurato
- `openai_api_key` - Chiave API OpenAI
- `google_api_key` - Chiave API Google
- `routing_mode` - Modalità di routing
- `setup_completed` - Flag di completamento setup

Se l'80% o più di queste impostazioni sono ai valori predefiniti, il sistema considera che è avvenuto un reset.

## 🛠️ Utilizzo Manuale

### Script di Gestione

È disponibile uno script per gestire manualmente i backup:

```bash
# Crea backup delle impostazioni attuali
php tools/manage-settings.php backup

# Ripristina dalle impostazioni di backup
php tools/manage-settings.php restore

# Mostra informazioni sul backup esistente
php tools/manage-settings.php info

# Cancella il backup esistente
php tools/manage-settings.php clear
```

### Esempi di Utilizzo

#### Scenario 1: Aggiornamento Plugin
```bash
# 1. L'utente aggiorna il plugin tramite WordPress Admin
# 2. Il sistema crea automaticamente un backup prima dell'aggiornamento
# 3. Dopo l'aggiornamento, rileva che le impostazioni sono state resettate
# 4. Ripristina automaticamente le impostazioni dal backup
# 5. Aggiunge eventuali nuove opzioni con valori predefiniti
```

#### Scenario 2: Problemi con le Impostazioni
```bash
# 1. L'utente nota che le impostazioni sono sbagliate
# 2. Controlla se esiste un backup
php tools/manage-settings.php info

# 3. Ripristina le impostazioni corrette
php tools/manage-settings.php restore

# 4. Verifica che tutto funzioni correttamente
```

## 🔍 Debug e Logging

Il sistema registra le seguenti operazioni nei log di WordPress:

- `FPML: Settings backup created successfully` - Backup creato
- `FPML: Settings restored from backup successfully` - Ripristino completato
- `FPML: New settings options migrated successfully` - Migrazione nuove opzioni

Per abilitare i log, aggiungi al `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

## ⚙️ Configurazione Avanzata

### Hooks Disponibili

```php
// Prima dell'attivazione del plugin
do_action( 'fpml_before_activation' );

// Dopo l'inizializzazione del plugin
do_action( 'fpml_after_initialization' );
```

### Personalizzazione

Per personalizzare il comportamento, puoi estendere la classe:

```php
class My_Custom_Migration extends FPML_Settings_Migration {
    
    protected function settings_are_defaults( $current_settings, $defaults ) {
        // Logica personalizzata per determinare se le impostazioni sono ai valori predefiniti
        return parent::settings_are_defaults( $current_settings, $defaults );
    }
}
```

## 🚨 Risoluzione Problemi

### Le Impostazioni Non Vengono Ripristinate

1. **Verifica il backup**:
   ```bash
   php tools/manage-settings.php info
   ```

2. **Controlla i log di WordPress** per messaggi di errore

3. **Forza il ripristino**:
   ```bash
   php tools/manage-settings.php restore
   ```

### Il Backup Non Viene Creato

1. **Verifica i permessi** del database
2. **Controlla la memoria** disponibile per PHP
3. **Controlla i log** per errori di salvataggio

### Conflitti con Altri Plugin

Se altri plugin interferiscono con il sistema:

1. **Disattiva temporaneamente** altri plugin
2. **Ripristina le impostazioni**
3. **Riattiva gli altri plugin** uno alla volta

## 📊 Monitoraggio

### Opzioni del Database

- `fpml_settings_backup` - Dati del backup (timestamp, versione, impostazioni)
- `fpml_settings_migration_version` - Versione della migrazione corrente
- `fpml_settings` - Impostazioni attuali del plugin

### Struttura del Backup

```php
array(
    'timestamp' => '2025-01-15 10:30:00',
    'version'   => '0.4.1',
    'settings'  => array(
        'provider' => 'openai',
        'openai_api_key' => 'sk-...',
        // ... altre impostazioni
    )
)
```

## 🔒 Sicurezza

- Le chiavi API sono crittografate nel backup utilizzando `FPML_Secure_Settings`
- Il backup viene sanitizzato prima del salvataggio
- Solo gli amministratori possono accedere allo script di gestione
- I backup non contengono dati sensibili non crittografati

## 📈 Performance

- Il backup viene creato solo durante gli aggiornamenti
- Il ripristino avviene solo se necessario
- Le operazioni sono ottimizzate per database di grandi dimensioni
- Utilizza transazioni del database per garantire la consistenza

## 🔄 Versioni

- **v0.4.1**: Implementazione iniziale del sistema di migrazione
- **Prossime versioni**: Migrazione automatica tra versioni specifiche
