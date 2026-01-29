# Errore Critico - Pagina Diagnostics

## Problema
**Errore**: 504 Gateway Timeout  
**Pagina**: `/wp-admin/admin.php?page=fpml-settings&tab=diagnostics`  
**Data**: 2025-12-08

## Causa Probabile
La pagina Diagnostics chiama `$plugin->get_diagnostics_snapshot()` che potrebbe:
1. Eseguire query database pesanti
2. Analizzare molti log
3. Calcolare statistiche complesse
4. Causare timeout del server

## Fix Temporaneo
Aggiungere timeout e caching al metodo `get_diagnostics_snapshot()`.

## Fix Permanente
1. Implementare caching per lo snapshot
2. Limitare query database
3. Eseguire calcoli in background
4. Aggiungere paginazione per log

## Status
⚠️ **DA INVESTIGARE** - Il metodo potrebbe essere troppo pesante per essere eseguito sincrono.





