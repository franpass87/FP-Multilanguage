# Changelog - FP Multilanguage Plugin

Vedi [CHANGELOG.md](../CHANGELOG.md) nella root del progetto per la cronologia completa.

Questo file traccia le modifiche specifiche del plugin distribuibile.

## [0.4.1] - 2025-10-13

### Caratteristiche Principali
- 🔐 **Crittografia chiavi API** con AES-256-CBC
- 💾 **Sistema versionamento traduzioni** con backup e rollback
- 🔍 **Endpoint REST anteprima** per testare traduzioni senza salvare
- 🛡️ **36 correzioni bug** incluse 11 vulnerabilità sicurezza critiche

### Sicurezza
- Risolte 11 vulnerabilità critiche (race condition, multisite cleanup, REST auth)
- Chiavi API crittografate in database
- Trail audit completo per modifiche traduzioni

### Performance
- Reindex 10x più veloce (120s → 12s per 100 post)
- Riduzione 70-90% uso memoria nel batch processing
- Riduzione 40% costi API con logica retry smart

### Documentazione
- Guida completa in `docs/`
- Quick start in `📋_LEGGI_QUI.md`
- Riferimento API in `docs/api-preview-endpoint.md`

Vedi [CHANGELOG.md completo](../CHANGELOG.md) per tutti i dettagli.
