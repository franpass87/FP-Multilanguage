# Troubleshooting

## Errori provider

- **HTTP 401/403**: verificare le API key in Impostazioni → Provider. Abilitare log WP (`WP_DEBUG_LOG`) per controllare i messaggi salvati da `FPMultilanguage\Services\Logger`.
- **Timeout**: aumentare `timeout` nei filtri `fp_multilanguage_provider_settings` oppure ridurre il numero di richieste concorrenti.
- **Quota superata**: controllare la tab Quote nella pagina impostazioni. È possibile azzerare le statistiche cancellando l’opzione `fp_multilanguage_quota`.

## Traduzioni mancanti

- Controllare se la lingua corrente coincide con la sorgente (nessuna traduzione necessaria).
- Verificare che il meta `_fp_multilanguage_translations` contenga i dati per la lingua richiesta.
- Utilizzare il comando `wp fp-multilanguage translate <ID>` per forzare una rigenerazione.

## Widget switcher

- Se non compare nell’elenco widget assicurarsi che `widgets_init` venga eseguito (alcuni temi headless potrebbero disabilitare l’area widget).
- Per personalizzare il markup utilizzare lo shortcode e sovrascrivere gli stili con CSS personalizzato.

## Dynamic strings

- Verificare che gli elementi HTML abbiano attributo `data-fp-translatable` coerente con la chiave generata (`sha1(context|stringa)`).
- In ambiente headless, includere `dynamic-translations.js` manualmente e passare i dati necessari a `fpMultilanguageDynamic`.

Per problemi non elencati aprire una issue con log dettagliati.
