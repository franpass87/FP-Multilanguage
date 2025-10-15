# 🚀 Guida Setup OpenAI con Billing

## ✅ Passo 1: Verifica/Crea la Chiave API

1. Vai su: **https://platform.openai.com/api-keys**
2. Clicca su **"Create new secret key"**
3. Dai un nome alla chiave (es: "WordPress FP Multilanguage")
4. **IMPORTANTE:** Copia la chiave SUBITO (formato: `sk-proj-...` o `sk-...`)
5. Salvala in un posto sicuro (non potrai vederla di nuovo)

## 💳 Passo 2: Configura il Metodo di Pagamento

1. Vai su: **https://platform.openai.com/account/billing/overview**

2. Clicca su **"Add payment details"**

3. Inserisci i dati della tua carta di credito:
   - Numero carta
   - Data scadenza
   - CVV
   - Indirizzo di fatturazione

4. Clicca **"Save payment method"**

## 💰 Passo 3: Carica Crediti

1. Sempre nella pagina billing, clicca **"Add to credit balance"**

2. Scegli l'importo (consigliato per iniziare):
   - **$10** → ~66.000 caratteri tradotti
   - **$20** → ~133.000 caratteri tradotti
   - **$50** → ~333.000 caratteri tradotti

3. Clicca **"Continue"** e conferma il pagamento

4. **ATTENDI 1-2 MINUTI** affinché i crediti vengano attivati

## 🔧 Passo 4: Configura WordPress

1. Vai su **WordPress Admin** → **FP Multilanguage** → **Impostazioni** → **Generali**

2. Incolla la chiave API nel campo **"Chiave OpenAI"**

3. Verifica che il modello sia: **gpt-4o-mini** (è il più economico e veloce)

4. Seleziona **OpenAI** come "Provider predefinito"

5. Clicca **"Salva modifiche"**

## ✅ Passo 5: Verifica che Funzioni

### Metodo 1: Pulsante "Verifica Billing" (NUOVO!)

1. Nella stessa pagina, clicca il pulsante **"Verifica Billing"** accanto alla chiave OpenAI
2. Dovresti vedere: ✅ **"API key valida e billing configurato correttamente"**
3. Se vedi un errore, leggi il messaggio dettagliato con le istruzioni

### Metodo 2: Test Provider

1. Vai su **FP Multilanguage** → **Diagnostica**
2. Clicca **"Test provider"**
3. Dovresti vedere la traduzione di una frase di test
4. Verifica latenza e costo stimato

### Metodo 3: Via WP-CLI (se disponibile)

```bash
wp fpml test openai
```

## 🎯 Cosa Aspettarsi

### ✅ Se Tutto Va Bene

Vedrai:
```
✅ API key valida e billing configurato correttamente.

Provider: OpenAI
Latenza: ~0.8s
Caratteri: 52
Costo stimato: 0.0078 €
```

### ❌ Se C'è un Problema

**Errore: "Insufficient quota"**
→ I crediti non sono ancora attivati. Attendi 1-2 minuti e riprova.

**Errore: "Invalid API key"**
→ Hai copiato male la chiave. Creane una nuova e copia/incolla con attenzione.

**Errore: "Rate limit exceeded"**
→ Hai fatto troppi test. Attendi 1 minuto.

## 💰 Monitoraggio Costi

### Controlla i Crediti Rimanenti

Vai su: **https://platform.openai.com/account/usage**

Vedrai:
- Crediti totali
- Crediti utilizzati
- Crediti rimanenti
- Grafici di utilizzo giornaliero

### Imposta Limiti di Spesa (Consigliato)

1. Vai su: **https://platform.openai.com/account/billing/limits**
2. Imposta un **"Hard limit"** mensile (es: $50/mese)
3. OpenAI bloccherà automaticamente le richieste se superi il limite
4. Riceverai email di notifica al 75%, 90%, 100%

### Attiva Notifiche

1. Vai su: **https://platform.openai.com/account/billing/overview**
2. Scorri fino a **"Email preferences"**
3. Attiva:
   - ✅ Usage threshold alerts
   - ✅ Monthly usage reports

## 📊 Stima Costi Reali

### Esempio Pratico con gpt-4o-mini

| Contenuto | Caratteri | Costo | Crediti $10 |
|-----------|-----------|-------|-------------|
| 1 articolo (1000 parole) | ~6.000 | $0.90 | 11 articoli |
| 10 pagine prodotto | ~15.000 | $2.25 | 4.4 batch |
| 100 post blog | ~600.000 | $90 | 1.1 batch |
| 1 sito completo (50 pagine) | ~300.000 | $45 | 2.2 siti |

### Confronto Modelli OpenAI

| Modello | Costo/1M char | Qualità | Velocità | Consigliato |
|---------|--------------|---------|----------|-------------|
| gpt-4o-mini | $15 | ⭐⭐⭐⭐⭐ | ⚡⚡⚡ | ✅ SI |
| gpt-4o | $150 | ⭐⭐⭐⭐⭐ | ⚡⚡ | Solo per contenuti critici |
| gpt-3.5-turbo | $50 | ⭐⭐⭐⭐ | ⚡⚡⚡ | Deprecato |

**Consiglio:** Usa **gpt-4o-mini** - È 10x più economico di gpt-4o con qualità quasi identica!

## 🔒 Sicurezza

### Proteggi la Tua Chiave API

1. **NON condividerla** con nessuno
2. **NON commitarla** su GitHub/repository pubblici
3. Il plugin la cripta automaticamente nel database
4. Se pensi sia compromessa, **revocala subito** e creane una nuova

### Revoca una Chiave Compromessa

1. Vai su: https://platform.openai.com/api-keys
2. Trova la chiave compromessa
3. Clicca **"Delete"** o l'icona del cestino
4. Crea una nuova chiave
5. Aggiornala in WordPress

## 🆘 Risoluzione Problemi

### "Billing setup is required"
→ Non hai ancora aggiunto un metodo di pagamento. Torna al Passo 2.

### "Insufficient quota" anche con crediti caricati
→ Attendi 2-3 minuti. Il sistema OpenAI impiega tempo ad attivare i crediti.

### "You don't have access to this model"
→ Il tuo account non ha accesso a gpt-4o-mini. Prova con "gpt-3.5-turbo".

### "Rate limit exceeded for default-gpt-4o-mini"
→ Troppi test in poco tempo. Attendi 60 secondi e riprova.

## 📞 Link Utili

- **Dashboard OpenAI:** https://platform.openai.com/
- **Billing & Usage:** https://platform.openai.com/account/billing/overview
- **API Keys:** https://platform.openai.com/api-keys
- **Limiti di Spesa:** https://platform.openai.com/account/billing/limits
- **Documentazione:** https://platform.openai.com/docs
- **Status OpenAI:** https://status.openai.com/

## ✅ Checklist Finale

Prima di iniziare a tradurre, verifica:

- [ ] Metodo di pagamento configurato
- [ ] Crediti caricati e attivati (attendi 1-2 min)
- [ ] Chiave API creata e copiata correttamente
- [ ] Chiave incollata in WordPress e salvata
- [ ] Provider "OpenAI" selezionato
- [ ] Modello impostato su "gpt-4o-mini"
- [ ] Test provider completato con successo ✅
- [ ] Pulsante "Verifica Billing" mostra ✅
- [ ] Limiti di spesa configurati (opzionale ma consigliato)
- [ ] Notifiche email attivate

## 🎉 Sei Pronto!

Se tutti i punti della checklist sono ✅, sei pronto per tradurre!

### Prossimi Passi:

1. Vai su **FP Multilanguage** → **Diagnostica**
2. Clicca **"Forza reindex"** per trovare tutti i contenuti da tradurre
3. Clicca **"Esegui batch ora"** per avviare le traduzioni
4. Monitora lo stato nella dashboard

Buona traduzione! 🚀
