# Phase 10 – Documentation & Release

_Data:_ ottobre 2024  \
_Responsabile:_ Francesco Passeri

## Attività svolte

- Bump versione del plugin a **1.2.0** e allineamento metadati (stable tag, package.json, QA report, roadmap, documentazione).
- Aggiornamento README/readme.txt con changelog completo dell'audit (sicurezza, performance, compatibilità Gutenberg 6.5, upgrade manager e runtime logger).
- Creazione pacchetto distribuzione `dist/fp-multilanguage-1.2.0.zip` comprensivo della cartella plugin.
- Generazione checksum `SHA256` per il pacchetto distribuzione e documentazione del percorso.

## Test eseguiti

- `composer lint`
- `composer stan`
- `composer test`
- `npm run build`

Tutti i comandi sono stati eseguiti con esito positivo.

## Output di release

- Pacchetto zip: `dist/fp-multilanguage-1.2.0.zip`
- Checksum: `dist/fp-multilanguage-1.2.0.zip.sha256` (SHA256 `5bf9e6c2cb0271ce3b2f7a111f228f96e8aaa3adbaa57cbf6a26ff3846b4d778`)

## Note

- Il `UpgradeManager` esegue ora il flush di cache e OPcache dopo ogni aggiornamento di versione.
- Il blocco Gutenberg utilizza `block.json` e il manifest `.asset.php` per garantire compatibilità con WordPress 6.5+.
- Il logger runtime resta attivo in produzione; per disattivarlo rimuovere il servizio `RuntimeLogger` dal container in un filtro dedicato.
