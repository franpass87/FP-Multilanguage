# FP Multilanguage

Plugin WordPress modulare per la gestione completa delle traduzioni di contenuti, stringhe dinamiche e SEO multilingua. Il progetto fornisce:

- Pagina impostazioni per configurare i provider di traduzione (Google Translate, DeepL) e le relative API key.
- Servizio di traduzione con caching, gestione delle quote e fallback manuale.
- Generazione automatica delle versioni tradotte di post e pagine tramite `save_post`, con esposizione dei contenuti localizzati tramite `the_content` e REST API.
- Traduzione di stringhe dinamiche (widget, menu, testi registrati) con strumenti JS per inserire correzioni manuali on-page.
- Funzionalità SEO dedicate: meta tag localizzati, tag `hreflang`, sitemap alternate e meta-box per personalizzare title/description per lingua.
- Test di integrazione minimi su PHPUnit per le parti core.

## Requisiti

- WordPress 6.0 o superiore.
- PHP 8.0 o superiore.
- Account/chiavi API per i provider di traduzione che si desidera utilizzare (Google Cloud Translation API, DeepL API Free/Pro).

## Installazione

1. Clonare questo repository e copiare la cartella `fp-multilanguage/` all'interno di `wp-content/plugins/` del proprio progetto WordPress.
2. (Opzionale) Installare le dipendenze di sviluppo per eseguire i test:
   ```bash
   composer install
   ```
3. Accedere alla Bacheca di WordPress e attivare il plugin **FP Multilanguage** dalla sezione **Plugin**.

## Configurazione

Dopo l'attivazione, aprire **Impostazioni → FP Multilanguage** per configurare:

- **Provider di traduzione:** abilitare uno o più provider e inserire le relative API key.
- **Lingue:** specificare lingua sorgente, lingue di destinazione e lingua di fallback manuale.
- **Traduzione automatica:** abilitare/disabilitare la traduzione automatica dei contenuti al salvataggio.

Le impostazioni sono salvate tramite `register_setting` e vengono sanificate automaticamente. L'aggiornamento delle opzioni invalida la cache delle traduzioni per garantire che le modifiche abbiano effetto immediato.

## Localizzazione

I file di traduzione (`.po`/`.mo`) del plugin devono essere posizionati nella cartella `fp-multilanguage/languages/`. Durante l'hook `init` di WordPress il plugin carica automaticamente il dominio `fp-multilanguage`, rendendo disponibili le stringhe localizzate per il backend e il frontend.

## Uso

### Post e pagine

- Salvando o aggiornando un post/pagina il plugin genera (se abilitato) le versioni tradotte per tutte le lingue di destinazione configurate e le salva in `post_meta` mantenendo le relazioni lingua/sorgente.
- Visualizzando un contenuto è possibile ottenere la traduzione passando il parametro `?fp_lang=xx` (es. `?fp_lang=it`). In assenza della traduzione, il plugin utilizza il servizio di traduzione oppure esegue il fallback manuale.
- Le traduzioni sono esposte anche nell'output REST (`/wp-json/wp/v2/posts/<id>` → campo `fp_multilanguage`).

### Stringhe dinamiche

- Il plugin aggancia filtri su `widget_title`, `widget_text`, `nav_menu_item_title` e `gettext` per tradurre le stringhe dinamiche.
- Per inserire correzioni manuali lato front-end è sufficiente aggiungere l'attributo `data-fp-translatable="<hash>"` all'elemento HTML (dove `<hash>` corrisponde a `sha1('stringa originale')`). Un doppio clic (solo per utenti con capacità `manage_options`) apre un prompt per salvare la traduzione manuale tramite AJAX; i valori sono persistiti e condivisi tra front-end e backend.

### Fallback manuale

- Le traduzioni manuali (contenuti o stringhe) sono salvate nelle opzioni e vengono sempre preferite rispetto ai provider esterni.
- È possibile aggiungere manualmente una traduzione registrando l'opzione `fp_multilanguage_manual_strings` o tramite gli appositi strumenti JS inclusi.

## Funzionalità SEO

- Meta box “SEO multilingua” (post/pagine) per definire meta title e meta description per ogni lingua.
- Output dinamico di `<meta name="description">`, tag `hreflang` e variante `x-default` in base alle lingue disponibili per il contenuto corrente.
- Integrazione con la sitemap di WordPress (`wp_sitemaps_posts_entry`) per aggiungere le URL alternate localizzate.
- Aggiornamento automatico del document title localizzato (`pre_get_document_title`) sfruttando traduzioni salvate o generandole on-the-fly tramite i provider.

## Hook e filtri utili

- `fp_multilanguage_current_language`: consente di forzare la lingua corrente (utile per temi/multisite).
- `fp_multilanguage_translation_fallback`: permette di personalizzare il fallback quando nessun provider è disponibile.
- `fp_multilanguage_quota_limits`: modifica i limiti di quota (richieste/caratteri) per singolo provider.
- `fp_multilanguage_translate_with_{provider}`: aggiunge provider di traduzione personalizzati.

## Test

Sono forniti test PHPUnit per il servizio di traduzione.

Eseguirli dall'ambiente di sviluppo:

```bash
composer install   # solo la prima volta
composer test
```

## Struttura del progetto

```
fp-multilanguage/
├── fp-multilanguage.php         # file principale del plugin
├── includes/
│   ├── Plugin.php                # bootstrap generale
│   ├── Settings.php              # pagina impostazioni e gestione opzioni
│   ├── TranslationService.php    # integrazione provider, caching, quote
│   ├── PostTranslationManager.php# gestione traduzione post/pagine
│   ├── DynamicStrings.php        # traduzione stringhe dinamiche + fallback JS
│   └── SEO.php                   # integrazione SEO multilingua
├── languages/                    # file di localizzazione del plugin (mo/po)
├── assets/
│   └── js/dynamic-translations.js
└── tests/
    ├── TranslationServiceTest.php
    ├── bootstrap.php
    └── stubs/wordpress.php
```

## Note finali

Il plugin è progettato per essere esteso: è possibile aggiungere nuovi provider tramite filtri, modificare i limiti di quota e integrare sistemi di routing multilingua esistenti sovrascrivendo il filtro `fp_multilanguage_current_language`.
