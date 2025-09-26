# FP Multilanguage – Guida all'aggiornamento (v1.2.0)

Questa guida descrive i passaggi consigliati per aggiornare FP Multilanguage alla versione **1.2.0**.

## Prerequisiti

- WordPress 6.0 o superiore (raccomandato 6.5+).
- PHP 7.4 o superiore (testato fino a PHP 8.2).
- Backup completo del database WordPress e della cartella `wp-content/uploads`.

## Procedura di aggiornamento

1. **Backup** – Esegui un backup completo del database e della cartella `wp-content/plugins/fp-multilanguage/`.
2. **Disattiva cache page/object** – Pulisci eventuali cache persistenti (Redis/Memcached, page cache) per evitare dati obsoleti.
3. **Aggiorna il plugin** – Carica il pacchetto `dist/fp-multilanguage-1.2.0.zip` e sovrascrivi la cartella esistente oppure utilizza l'aggiornamento automatico da WordPress.
4. **Verifica l'attivazione** – Assicurati che il plugin sia attivo. L'`UpgradeManager` eseguirà automaticamente:
   - bootstrap delle opzioni richieste;
   - flush delle cache WP (`wp_cache_flush`) e OPcache, se disponibili;
   - log delle operazioni di upgrade.
5. **Controlla le impostazioni** – Apri **Impostazioni → FP Multilanguage** e verifica che provider, lingue e quote siano correttamente caricati (ora serviti da object cache).
6. **Rigenera asset** (facoltativo) – Se utilizzi un CDN, invalida gli asset del blocco linguistico aggiornato (`language-switcher-block.js`).

## Novità rilevanti in 1.2.0

- Hardening sicurezza con `RestNonceValidator`, controlli capability e sanitizzazione input.
- Cache delle impostazioni e delle stringhe manuali con hook per invalidazione immediata.
- Compatibilità Gutenberg 6.5 tramite `block.json`, manifest `.asset.php` e supporto parametro `WP_Block`.
- Runtime logger per notice/exception e bootstrap modulare admin/pubblico.
- Routine di upgrade centralizzata con flush cache automatico.

## Post-aggiornamento

- Verifica i log `wp-content/debug.log` per eventuali avvisi inaspettati (il runtime logger registra anche su database/file a seconda della configurazione).
- Se utilizzi provider personalizzati, assicurati che i filtri `fp_multilanguage_provider_sequence` siano ancora corretti.
- Aggiorna eventuali build custom di asset frontend/admin dopo aver installato le nuove dipendenze (`npm install && npm run build`).

Per supporto aggiuntivo consulta `docs/audit/release.md` o contatta [info@francescopasseri.com](mailto:info@francescopasseri.com).
