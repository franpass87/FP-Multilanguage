# Riepilogo Problema Loop Infinito

## Problema
Il plugin `FP-SEO-AutoIndex` chiama `do_action('on_publish')` ripetutamente, causando un loop infinito che esaurisce la memoria PHP (512MB).

## Tentativi di Soluzione

### 1. Hook 'all' con priorità -99999
- **Implementato**: Sì
- **Funziona**: No
- **Motivo**: L'hook 'all' viene chiamato, ma quando rimuoviamo l'hook da `$wp_filter`, questo avviene DOPO che `do_action` ha già controllato se l'hook esiste (riga 498 di plugin.php). Inoltre, `FP-SEO-AutoIndex` continua a chiamare `do_action('on_publish')` anche dopo che abbiamo rimosso l'hook.

### 2. Hook 'on_publish' con priorità -9999
- **Implementato**: Sì
- **Funziona**: Parzialmente
- **Motivo**: Rileva il loop, ma non può impedire che `do_action` venga chiamato da altri plugin.

### 3. Rimozione da $wp_actions
- **Implementato**: Sì
- **Funziona**: No
- **Motivo**: `$wp_actions` viene incrementato PRIMA dell'hook 'all' (riga 485-489), quindi non possiamo impedirlo.

## Soluzione Necessaria

Il problema è che **non possiamo impedire che `do_action('on_publish')` venga chiamato** da altri plugin. Possiamo solo:
1. Rilevare il loop
2. Rimuovere gli hook
3. Ma `do_action` continua a essere chiamato

## Raccomandazione

**Disabilitare temporaneamente il plugin `FP-SEO-AutoIndex`** durante la pubblicazione, oppure modificare `FP-SEO-AutoIndex` per non chiamare `do_action('on_publish')` ripetutamente.

## Codice Attuale

Il codice attuale ha:
- Hook 'all' con priorità -99999 per intercettare PRIMA dell'esecuzione
- Hook 'on_publish' con priorità -9999 per rilevare loop
- Rimozione di `$wp_filter['on_publish']` e `$wp_actions['on_publish']`
- Soglia ultra-aggressiva (0.3 secondi)

Ma il problema persiste perché `FP-SEO-AutoIndex` continua a chiamare `do_action('on_publish')` anche dopo che abbiamo rimosso l'hook.


