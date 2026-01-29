# Getting Started - FP Multilanguage

**Version:** 0.10.0+  
**Last Updated:** 2025-01-XX

Guida rapida per iniziare a usare FP Multilanguage.

---

## 📋 Indice

- [Installazione](#installazione)
- [Configurazione Base](#configurazione-base)
- [Prima Traduzione](#prima-traduzione)
- [Dashboard](#dashboard)
- [Impostazioni Avanzate](#impostazioni-avanzate)

---

## Installazione

### Requisiti

- WordPress 5.8+
- PHP 8.0+
- MySQL 5.7+ o MariaDB 10.3+

### Passo 1: Installazione Plugin

1. Vai su **Plugin > Aggiungi nuovo**
2. Cerca "FP Multilanguage"
3. Clicca **Installa** e poi **Attiva**

### Passo 2: Verifica Installazione

Dopo l'attivazione, vedrai un nuovo menu **FP Multilanguage** nella sidebar di WordPress admin.

---

## Configurazione Base

### 1. Configura Provider Traduzione

1. Vai su **FP Multilanguage > Impostazioni > Generale**
2. Seleziona il **Provider** (OpenAI o Google Cloud Translation)
3. Inserisci la tua **API Key**
4. Clicca **Salva impostazioni**

### 2. Abilita Lingue

1. Vai su **FP Multilanguage > Impostazioni**
2. Seleziona le lingue che vuoi supportare
3. La lingua predefinita è **Italiano (IT)**
4. Aggiungi **Inglese (EN)** e altre lingue come desiderato

### 3. Configura Post Types Traducibili

1. Vai su **FP Multilanguage > Impostazioni > Contenuti**
2. Seleziona i post types che vuoi tradurre
3. Di default: **Post** e **Pagine** sono abilitati

---

## Prima Traduzione

### Metodo 1: Traduzione Manuale (Post Editor)

1. Vai su **Post > Tutti i post**
2. Apri un post da tradurre
3. Nella sidebar destra, trova la metabox **🌍 Traduzioni**
4. Clicca **🚀 Traduci in Inglese ORA**
5. Attendi la traduzione (viene mostrata una progress bar)
6. Una volta completata, vedrai il pulsante **Visualizza EN** e **Modifica EN**

### Metodo 2: Traduzione Automatica (Queue)

1. Vai su **FP Multilanguage > Dashboard**
2. Clicca **Reindex Contenuti** per aggiungere tutti i post alla coda
3. I post verranno tradotti automaticamente in background
4. Puoi monitorare lo stato nella sezione **Coda Traduzioni**

---

## Dashboard

### Statistiche Principali

Il **Dashboard** mostra:
- **Post Tradotti:** Numero totale di post con traduzione
- **In Coda:** Job in attesa di traduzione
- **Costo Stimato:** Stima costi API per traduzioni pending
- **Cache Hit Rate:** Percentuale di traduzioni servite dalla cache

### Azioni Rapide

- **Reindex Contenuti:** Aggiunge tutti i post non tradotti alla coda
- **Esegui Coda:** Processa manualmente i job in coda
- **Pulisci Coda:** Rimuove job vecchi completati

---

## Impostazioni Avanzate

### Cache

1. Vai su **FP Multilanguage > Impostazioni**
2. Sezione **Cache**
3. Configura **TTL Cache** (default: 1 ora)
4. Attiva/disattiva cache secondo necessità

### Queue Settings

1. Vai su **FP Multilanguage > Impostazioni**
2. Sezione **Coda**
3. Configura **Batch Size** (default: 10 job per batch)
4. Configura **Retention Days** (quanti giorni conservare job completati)

### Traduzione Automatica

1. Vai su **FP Multilanguage > Impostazioni**
2. Sezione **Traduzione Automatica**
3. Attiva **Traduci automaticamente su pubblicazione**
4. I nuovi post verranno automaticamente aggiunti alla coda

---

## Workflow Consigliato

### Per Nuovi Siti

1. ✅ Configura provider e API key
2. ✅ Abilita lingue desiderate
3. ✅ Configura post types traducibili
4. ✅ Esegui **Reindex Contenuti** per tradurre tutto
5. ✅ Monitora progresso nel Dashboard

### Per Post Esistenti

1. ✅ Crea/aggiorna contenuti in italiano
2. ✅ Usa **Traduci ORA** nella metabox per traduzione immediata
3. ✅ Oppure lascia che la coda processi automaticamente
4. ✅ Verifica traduzioni nella lista post (colonna "🌍 Traduzione")

---

## Verifica Traduzioni

### Nella Lista Post

1. Vai su **Post > Tutti i post**
2. Cerca la colonna **🌍 Traduzione**
3. Vedi lo stato della traduzione:
   - ✅ **Tradotto** - Traduzione completa disponibile
   - ⚠ **Parziale** - Traduzione incompleta
   - ⏳ **In corso** - Traduzione in elaborazione
   - ⚪ **Non tradotto** - Nessuna traduzione

### Visualizza Traduzione

1. Clicca su **Visualizza EN** nella metabox
2. Oppure usa la colonna "🌍 Traduzione" nella lista post
3. Clicca **Visualizza** per vedere il post tradotto nel frontend

---

## Prossimi Passi

- 📖 Leggi la [Guida Completa](./troubleshooting.md) per troubleshooting
- ❓ Consulta le [FAQ](./faq.md) per domande comuni
- 🛠 Esplora le [Impostazioni Avanzate](./developer-guide.md) per personalizzazioni

---

## 🔗 Link Utili

- [Troubleshooting](./troubleshooting.md)
- [FAQ](./faq.md)
- [Documentazione Tecnica](./api-reference.md)

---

**Ultimo aggiornamento:** 2025-01-XX  
**Versione Plugin:** 0.10.0+







