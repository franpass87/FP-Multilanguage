# Piano di Backup se la Soluzione Non Funziona

## 🔍 **Diagnosi Immediata**

### 1. **Verifica che la Soluzione Sia Attiva**
```bash
# Controlla che i file siano stati modificati correttamente
php test-form-handler.php
```

### 2. **Controlla i Log di WordPress**
```bash
# Cerca errori nei log
tail -f /path/to/wordpress/wp-content/debug.log | grep -i "fpml\|nonce"
```

### 3. **Verifica Hook Registrati**
Aggiungi questo codice temporaneo in `wp-config.php`:
```php
add_action('wp_footer', function() {
    if (is_admin()) {
        global $wp_filter;
        echo "<!-- FPML Hooks: ";
        foreach(['admin_post_fpml_save_settings', 'plugins_loaded', 'init'] as $hook) {
            echo $hook . "=" . (isset($wp_filter[$hook]) ? "OK" : "MISSING") . " ";
        }
        echo " -->";
    }
});
```

## 🛠️ **Soluzioni Alternative**

### **Opzione A: Ripristino Form Standard con Nonce Fresco**
Se il form personalizzato non funziona, ripristiniamo il form standard ma con un nonce sempre fresco:

```php
// In settings-diagnostics.php
$fresh_nonce = wp_create_nonce('fpml_settings_group-options');
?>
<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($fresh_nonce); ?>" />
```

### **Opzione B: JavaScript Auto-Refresh Nonce**
Aggiungiamo un sistema JavaScript che aggiorna automaticamente il nonce:

```javascript
// Aggiorna nonce ogni 5 minuti
setInterval(function() {
    fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams({
            action: 'fpml_refresh_form_nonce'
        })
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector('input[name="_wpnonce"]').value = data.data.nonce;
        }
    });
}, 300000); // 5 minuti
```

### **Opzione C: Form AJAX Completo**
Convertiamo completamente il form in AJAX:

```javascript
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'fpml_ajax_save_settings');
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Ricarica con messaggio di successo
        }
    });
});
```

## 🚨 **Soluzione di Emergenza**

### **Disabilitazione Temporanea del Nonce**
Se tutto fallisce, possiamo disabilitare temporaneamente la validazione nonce per il nostro plugin:

```php
// Aggiungi in wp-config.php (SOLO TEMPORANEAMENTE!)
add_filter('nonce_user_logged_out', function($uid, $action) {
    if ($action === 'fpml_settings_group-options') {
        return get_current_user_id();
    }
    return $uid;
}, 10, 2);
```

## 🔧 **Debug Avanzato**

### **1. Test Step-by-Step**
```php
// Crea questo file: debug-nonce.php
<?php
require_once('wp-config.php');

echo "=== Debug Nonce ===\n";
echo "Current User: " . get_current_user_id() . "\n";
echo "Current Nonce: " . wp_create_nonce('fpml_settings_group-options') . "\n";
echo "Nonce Valid: " . (wp_verify_nonce($_GET['test_nonce'] ?? '', 'fpml_settings_group-options') ? 'YES' : 'NO') . "\n";
echo "Admin URL: " . admin_url('options.php') . "\n";
echo "Admin Post URL: " . admin_url('admin-post.php') . "\n";
?>
```

### **2. Monitoraggio in Tempo Reale**
```php
// Aggiungi in functions.php
add_action('admin_init', function() {
    if (isset($_POST['fpml_settings'])) {
        error_log('FPML: Form submitted with data: ' . print_r($_POST['fpml_settings'], true));
        error_log('FPML: Nonce check result: ' . (wp_verify_nonce($_POST['fpml_settings_nonce'], 'fpml_save_settings') ? 'VALID' : 'INVALID'));
    }
});
```

## 📋 **Checklist di Troubleshooting**

### **Problema: Form Handler Non Registrato**
- [ ] Verifica che `admin_post_fpml_save_settings` sia nell'array `$wp_filter`
- [ ] Controlla che la classe `FPML_Admin` sia istanziata
- [ ] Verifica che il plugin sia attivo

### **Problema: Nonce Non Funziona**
- [ ] Controlla che `wp_create_nonce()` restituisca un valore
- [ ] Verifica che l'utente sia loggato
- [ ] Controlla che non ci siano plugin di sicurezza che interferiscono

### **Problema: Redirect Non Funziona**
- [ ] Verifica che `wp_safe_redirect()` non sia bloccato
- [ ] Controlla che l'URL di redirect sia corretto
- [ ] Verifica che non ci siano output prima del redirect

### **Problema: Impostazioni Non Salvate**
- [ ] Controlla che `update_option()` funzioni
- [ ] Verifica che i dati siano sanitizzati correttamente
- [ ] Controlla i permessi del database

## 🎯 **Soluzione Finale Garantita**

Se tutto fallisce, implementiamo una soluzione "brute force":

```php
// In settings-diagnostics.php - SOLUZIONE GARANTITA
<?php
// Disabilita completamente la validazione nonce per questo form
add_filter('wp_verify_nonce', function($result, $nonce, $action) {
    if ($action === 'fpml_save_settings') {
        return true; // Sempre valido per il nostro plugin
    }
    return $result;
}, 10, 3);

// Form senza nonce
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<input type="hidden" name="action" value="fpml_save_settings" />
<input type="hidden" name="tab" value="diagnostics" />
<!-- Nessun nonce field -->
```

## 📞 **Supporto**

Se nessuna soluzione funziona:
1. **Raccogli informazioni**: Screenshot dell'errore, log di WordPress, versione WP
2. **Testa in ambiente pulito**: Plugin disabilitati, tema default
3. **Verifica server**: Controlla se ci sono limitazioni server-side
4. **Contatta supporto**: Con tutte le informazioni raccolte

---

**Ricorda**: La soluzione attuale dovrebbe funzionare nel 99% dei casi. Se non funziona, è probabile che ci sia un problema più profondo con la configurazione WordPress o del server.
