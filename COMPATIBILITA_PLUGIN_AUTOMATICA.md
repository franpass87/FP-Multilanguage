# 🔌 Compatibilità Plugin Automatica

## ✅ Risposta alla Tua Domanda

**Sì!** FP Multilanguage ora include un **sistema di rilevamento automatico** che:

1. ✅ **Rileva automaticamente** i plugin installati
2. ✅ **Registra i custom fields** senza configurazione manuale  
3. ✅ **Traduce tutto automaticamente** senza dover specificare ogni servizio
4. ✅ **Supporta 20+ plugin** out-of-the-box

---

## 🎯 Plugin Già Supportati (Auto-Rilevamento)

### 🔍 **SEO** (4 plugin)
- **Yoast SEO** ✅ - Tutti i campi SEO, Open Graph, Twitter Cards
- **Rank Math SEO** ✅ - Meta, Focus Keyword, Social
- **All in One SEO** ✅ - Completo
- **SEOPress** ✅ - Completo

### 🎨 **Page Builders** (4 plugin)
- **WPBakery** ✅ - Shortcode preservati
- **Elementor** ✅ - JSON data parsing
- **Beaver Builder** ✅ - Data e draft
- **Oxygen Builder** ✅ - Shortcodes e JSON

### 🛒 **E-commerce** (2 plugin)
- **WooCommerce** ✅ - Prodotti, attributi, categorie
- **Easy Digital Downloads** ✅ - Completo

### 📝 **Forms** (4 plugin)
- **WPForms** ✅ - Shortcode preservato
- **Gravity Forms** ✅ - Messaggi, bottoni
- **Ninja Forms** ✅ - Label, placeholder, help text
- **Contact Form 7** ✅ - Shortcode preservato

### ⚙️ **Custom Fields** (3 plugin)
- **Advanced Custom Fields (ACF)** ✅ - Detection dinamica completa
- **Meta Box** ✅ - API integration
- **Pods** ✅ - Dynamic fields

### 🎨 **Temi**
- **Salient** ✅ - Supporto nativo con CSS personalizzati
- **Astra, GeneratePress, OceanWP** ✅
- **Divi, Avada, Enfold** ✅
- **E molti altri...**

### 📅 **Altri** (2 plugin)
- **The Events Calendar** ✅
- **LearnDash** ✅

---

## 🚀 Come Funziona

### 1. **Zero Configurazione**

Il plugin rileva automaticamente tutto:

```
✅ WPBakery rilevato → Shortcode preservati automaticamente
✅ Salient rilevato → CSS e menu integrati automaticamente
✅ Yoast SEO rilevato → Meta SEO tradotti automaticamente
✅ WooCommerce rilevato → Prodotti tradotti automaticamente
✅ WPForms rilevato → Form preservati automaticamente
```

### 2. **Visualizza Plugin Rilevati**

Vai in **Impostazioni → FP Multilanguage → Compatibilità Plugin**

Vedrai:
- ✅ Elenco plugin rilevati
- ✅ Numero di campi tradotti per ogni plugin
- ✅ Stato di compatibilità
- ✅ Test connettività

### 3. **Aggiorna Rilevamento**

Quando installi un nuovo plugin:

1. Vai in **Compatibilità Plugin**
2. Clicca **"Rileva Plugin"**
3. ✅ Il nuovo plugin viene rilevato automaticamente

---

## 💡 Esempi Pratici

### Esempio 1: Uso WPBakery + Yoast SEO + WooCommerce

```
✅ WPBakery → Shortcode preservati ✓
✅ Yoast SEO → Meta title/description tradotti ✓
✅ WooCommerce → Prodotti tradotti ✓

Risultato: TUTTO tradotto automaticamente!
```

### Esempio 2: Uso Salient + ACF + Elementor

```
✅ Salient → Bandierine nel menu ✓
✅ ACF → Campi personalizzati tradotti ✓
✅ Elementor → Layout preservati ✓

Risultato: Sito completamente multilingua!
```

### Esempio 3: Aggiungo Rank Math SEO

```
1. Installo Rank Math
2. Vado in FP Multilanguage → Compatibilità Plugin
3. Clicca "Rileva Plugin"
4. ✅ Rank Math rilevato automaticamente
5. ✅ Tutti i meta SEO vengono tradotti

Zero configurazione necessaria!
```

---

## 🛠️ Aggiungere Plugin Personalizzati

Se usi un plugin **non** nella lista, puoi aggiungerlo facilmente:

### Metodo 1: Codice Semplice (nel tema)

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['mio_plugin'] = array(
        'name'   => 'Il Mio Plugin',
        'check'  => array( 'class' => 'MioPlugin' ),
        'fields' => array(
            '_mio_campo_1',
            '_mio_campo_2',
            '_mio_seo_title',
        ),
    );
    
    return $rules;
} );
```

Dove mettere questo codice:
- `functions.php` del tuo tema child
- Plugin personalizzato
- Code Snippets plugin

### Metodo 2: Detection Dinamica

Per plugin con campi che cambiano:

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['mio_plugin_avanzato'] = array(
        'name'     => 'Plugin Avanzato',
        'check'    => array( 'class' => 'PluginAvanzato' ),
        'callback' => function() {
            // Rileva campi dinamicamente
            $campi_custom = get_option( 'miei_campi' );
            return array_column( $campi_custom, 'meta_key' );
        },
    );
    
    return $rules;
} );
```

---

## 📊 Dashboard Compatibilità

Il plugin include una **dashboard completa** in:

**Impostazioni → FP Multilanguage → Compatibilità Plugin**

### Cosa puoi vedere:
- 📊 Numero plugin rilevati
- ✅ Lista plugin compatibili
- 🔍 Campi personalizzati per ogni plugin
- 🧪 Test connettività
- 📖 Documentazione integrata

### Azioni disponibili:
- 🔄 **Rileva Plugin** - Forza nuovo scan
- 👁️ **Mostra Campi** - Vedi i custom fields rilevati
- 🧪 **Test** - Verifica compatibilità

---

## 🎓 Tutorial Rapido

### Per WPBakery + Salient + Yoast SEO (il tuo setup)

```
1. ✅ WPBakery è già supportato
   → Gli shortcode [vc_*] vengono preservati automaticamente

2. ✅ Salient è già supportato  
   → Bandierine nel menu automatiche
   → CSS personalizzati applicati automaticamente

3. ✅ Yoast SEO è già supportato
   → Title, Description, Open Graph → Tutti tradotti automaticamente

4. ✅ WPForms è già supportato (base)
   → Shortcode form preservati

5. ✅ WooCommerce è già supportato
   → Prodotti, attributi, categorie → Tutti tradotti
```

**Non devi fare NULLA!** È tutto automatico! 🎉

---

## ❓ FAQ

### Il plugin rileva automaticamente TUTTI i custom fields?

No, solo quelli dei plugin supportati. Ma puoi:
- Aggiungere regole custom (vedi sopra)
- Usare il filtro `fpml_meta_whitelist` per campi singoli

### Cosa succede se installo un nuovo plugin dopo?

1. Il plugin lo rileva automaticamente all'attivazione
2. Oppure clicca "Rileva Plugin" manualmente
3. I campi vengono aggiunti alla traduzione automatica

### Posso disabilitare il rilevamento per alcuni plugin?

Sì:

```php
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    // Rimuovi Yoast SEO dal rilevamento
    unset( $rules['yoast'] );
    return $rules;
} );
```

### Impatta le performance?

No! Il rilevamento avviene:
- Una volta all'attivazione del plugin
- Risultati salvati in cache
- Zero overhead in produzione

---

## 🆘 Supporto

### Plugin non rilevato?

1. Vai in **Compatibilità Plugin**
2. Clicca **"Rileva Plugin"**
3. Se ancora non appare, aggiungi regola custom (vedi sopra)

### Campi non tradotti?

1. Verifica che il plugin sia rilevato
2. Controlla i campi in "Mostra Campi"
3. Se mancano, aggiungi manualmente:

```php
add_filter( 'fpml_meta_whitelist', function( $whitelist ) {
    $whitelist[] = '_mio_campo_mancante';
    return $whitelist;
} );
```

---

## 📚 Documentazione Completa

- **[Plugin Compatibility Guide (EN)](docs/plugin-compatibility.md)** - Guida completa tecnica
- **[README.md](README.md)** - Documentazione generale
- **[CHANGELOG.md](CHANGELOG.md)** - Novità e aggiornamenti

---

## ✅ Conclusione

### Per il Tuo Setup (WPBakery + Salient + Yoast SEO + WPForms + WooCommerce):

✅ **TUTTO È GIÀ SUPPORTATO!**

Non serve configurare niente. Il plugin:
1. Rileva automaticamente WPBakery, Salient, Yoast, WPForms, WooCommerce
2. Registra automaticamente i custom fields
3. Traduce automaticamente tutto

**Zero configurazione. Zero problemi. Zero stress.** 🎉

### Prossimi Passi:

1. ✅ Installa/Aggiorna FP Multilanguage
2. ✅ Vai in **Compatibilità Plugin** per vedere tutto rilevato
3. ✅ Inizia a tradurre - funziona tutto automaticamente!

---

**Creato con ❤️ per FP Multilanguage v0.4.2+**

Made by [Francesco Passeri](https://francescopasseri.com)
