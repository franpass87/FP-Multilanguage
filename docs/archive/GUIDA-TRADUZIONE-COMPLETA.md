# Guida Completa alla Traduzione - FP Multilanguage

**Data:** 19 Novembre 2025  
**Versione:** 0.9.6

## 🎯 Come Tradurre Tutti gli Elementi del Sito

### 📍 Accesso all'Interfaccia

1. **Vai in WordPress Admin**
2. **Clicca su "FP Multilanguage"** nel menu laterale
3. **Seleziona il tab "🌐 Traduzioni"**

Oppure vai direttamente a:
```
WordPress Admin → FP Multilanguage → Tab "Traduzioni"
```

### 🚀 Traduzione Automatica

L'interfaccia mostra **13 box** per tradurre diversi elementi del sito. Ogni box ha un pulsante **"🚀 Traduci [Elemento]"** che traduce automaticamente tutti gli elementi di quella categoria.

#### 1. **Menu** 🍔
- **Cosa traduce**: Titoli degli elementi menu
- **Come usare**: Clicca "🚀 Traduci Menu"
- **Risultato**: Tutti i titoli dei menu vengono tradotti e salvati
- **Dove si vede**: Menu di navigazione su `/en/`

#### 2. **Widget** 📦
- **Cosa traduce**: Titoli e contenuti dei widget
- **Come usare**: Clicca "🚀 Traduci Widget"
- **Risultato**: Tutti i widget vengono tradotti
- **Dove si vede**: Sidebar e aree widget su `/en/`

#### 3. **Opzioni Tema** 🎨
- **Cosa traduce**: Opzioni del tema Salient (header, footer, copyright, CTA)
- **Come usare**: Clicca "🚀 Traduci Opzioni Tema"
- **Risultato**: Testi del tema tradotti
- **Dove si vede**: Header, footer e altre aree del tema su `/en/`

#### 4. **Plugin** 🔌
- **Cosa traduce**: Stringhe di plugin comuni (WooCommerce, Contact Form 7)
- **Come usare**: Clicca "🚀 Traduci Plugin"
- **Risultato**: Titoli e stringhe plugin tradotte
- **Dove si vede**: Pagine plugin su `/en/`

#### 5. **Impostazioni Sito** ⚙️
- **Cosa traduce**: Nome del sito (Site Title) e Tagline
- **Come usare**: Clicca "🚀 Traduci Impostazioni Sito"
- **Risultato**: Site Title e Tagline tradotti
- **Dove si vede**: Header, title tag, breadcrumb su `/en/`

#### 6. **Media** 🖼️
- **Cosa traduce**: Alt text, captions e descrizioni delle immagini
- **Come usare**: Clicca "🚀 Traduci Media"
- **Risultato**: Tutte le immagini con alt text, caption e descrizioni tradotte
- **Dove si vede**: Immagini nel contenuto su `/en/`

#### 7. **Commenti** 💬
- **Cosa traduce**: Contenuto dei commenti
- **Come usare**: Clicca "🚀 Traduci Commenti"
- **Risultato**: Tutti i commenti approvati tradotti
- **Dove si vede**: Sezione commenti su `/en/`

#### 8. **Customizer** 🎛️
- **Cosa traduce**: Opzioni del personalizzatore tema
- **Come usare**: Clicca "🚀 Traduci Customizer"
- **Risultato**: Opzioni customizer tradotte
- **Dove si vede**: Elementi personalizzati del tema su `/en/`

#### 9. **Archivi** 📚
- **Cosa traduce**: Titoli e descrizioni di categorie, tag, autori, date, custom taxonomies
- **Come usare**: Clicca "🚀 Traduci Archivi"
- **Risultato**: Tutti gli archivi tradotti
- **Dove si vede**: Pagine archivio su `/en/`

#### 10. **Risultati Ricerca** 🔍
- **Cosa traduce**: Messaggi "Nessun risultato", "X risultati trovati", etc.
- **Come usare**: Clicca "🚀 Traduci Ricerca"
- **Risultato**: Messaggi ricerca tradotti
- **Dove si vede**: Pagina risultati ricerca su `/en/`

#### 11. **Pagine 404** ❌
- **Cosa traduce**: Titolo e messaggi delle pagine 404
- **Come usare**: Clicca "🚀 Traduci 404"
- **Risultato**: Messaggi 404 tradotti
- **Dove si vede**: Pagine 404 su `/en/`

#### 12. **Breadcrumb** 🍞
- **Cosa traduce**: Etichette breadcrumb (Home, Category, Tag, etc.)
- **Come usare**: Clicca "🚀 Traduci Breadcrumb"
- **Risultato**: Etichette breadcrumb tradotte
- **Dove si vede**: Breadcrumb su `/en/` (Yoast SEO, Rank Math, AIOSEO)

#### 13. **Form** 📝
- **Cosa traduce**: Labels e placeholders dei form (Contact Form 7, WPForms)
- **Come usare**: Clicca "🚀 Traduci Form"
- **Risultato**: Labels e placeholders tradotti
- **Dove si vede**: Form su `/en/`

#### 14. **Autori** 👤
- **Cosa traduce**: Biografie degli autori
- **Come usare**: Clicca "🚀 Traduci Autori"
- **Risultato**: Biografie autori tradotte
- **Dove si vede**: Pagine autore su `/en/`

## 📋 Processo di Traduzione

### Passo 1: Verifica Configurazione
Prima di tradurre, assicurati che:
- ✅ Il provider di traduzione sia configurato (OpenAI, etc.)
- ✅ L'API key sia valida
- ✅ Il sito sia accessibile

### Passo 2: Traduzione Automatica
1. Vai in **FP Multilanguage → Traduzioni**
2. Clicca i pulsanti **"🚀 Traduci [Elemento]"** uno alla volta
3. Attendi il completamento (vedrai un messaggio di successo)
4. Ripeti per tutti gli elementi che vuoi tradurre

### Passo 3: Verifica
1. Vai su `/en/` nel frontend
2. Verifica che gli elementi siano tradotti
3. Se necessario, ritraducili cliccando di nuovo il pulsante

## 🔄 Ritraduzione

Puoi ritradurre qualsiasi elemento in qualsiasi momento:
- Clicca di nuovo il pulsante "🚀 Traduci [Elemento]"
- Le traduzioni esistenti verranno sovrascritte con nuove traduzioni

## 💾 Memorizzazione

Le traduzioni vengono salvate come:
- **Opzioni WordPress**: `_fpml_en_option_*`
- **Post Meta**: `_fpml_en_*` (per media, etc.)
- **Comment Meta**: `_fpml_en_*` (per commenti)
- **User Meta**: `_fpml_en_*` (per autori)

## 🎯 Ordine Consigliato di Traduzione

Per un'esperienza ottimale, traduci in questo ordine:

1. **Impostazioni Sito** (Site Title, Tagline)
2. **Menu** (Navigazione principale)
3. **Widget** (Sidebar, footer)
4. **Opzioni Tema** (Header, footer, copyright)
5. **Archivi** (Categorie, tag)
6. **Media** (Alt text, captions)
7. **Form** (Labels, placeholders)
8. **Breadcrumb** (Navigazione secondaria)
9. **Risultati Ricerca** (Messaggi ricerca)
10. **Pagine 404** (Messaggi errore)
11. **Commenti** (Contenuto commenti)
12. **Autori** (Biografie)
13. **Customizer** (Opzioni personalizzate)
14. **Plugin** (Stringhe plugin)

## ⚠️ Note Importanti

### Traduzione Automatica
- Le traduzioni usano il **provider configurato** (es. OpenAI)
- Il costo dipende dal numero di elementi da tradurre
- Le traduzioni vengono salvate nel database WordPress

### Performance
- La traduzione di molti elementi può richiedere tempo
- Non chiudere la pagina durante la traduzione
- Se la traduzione fallisce, riprova

### Cache
- Dopo la traduzione, potrebbe essere necessario **pulire la cache**
- Se usi plugin di cache, svuota la cache dopo la traduzione

### Verifica
- Controlla sempre su `/en/` che le traduzioni siano visibili
- Se non vedi le traduzioni, verifica che:
  - Sei su `/en/` (non su `/`)
  - La cache è stata pulita
  - Le traduzioni sono state salvate correttamente

## 🐛 Risoluzione Problemi

### Le traduzioni non appaiono su `/en/`
1. Verifica di essere su `/en/`
2. Pulisci la cache
3. Controlla che le traduzioni siano state salvate (vedi database)

### Errore durante la traduzione
1. Verifica che l'API key sia valida
2. Controlla i log del plugin
3. Riprova la traduzione

### Traduzioni incomplete
1. Ritraducili cliccando di nuovo il pulsante
2. Verifica che tutti gli elementi siano stati processati

## 📊 Statistiche

Dopo ogni traduzione, vedrai un messaggio con:
- Numero di elementi tradotti
- Stato della traduzione (successo/errore)

Esempio:
```
✅ 15 elementi menu tradotti.
✅ 8 widget tradotti.
✅ 3 opzioni tema tradotte.
```

## ✅ Checklist Completa

- [ ] Site Title e Tagline tradotti
- [ ] Menu tradotti
- [ ] Widget tradotti
- [ ] Opzioni Tema tradotte
- [ ] Plugin tradotti
- [ ] Media tradotti (alt text, captions)
- [ ] Commenti tradotti
- [ ] Customizer tradotto
- [ ] Archivi tradotti
- [ ] Ricerca tradotta
- [ ] 404 tradotte
- [ ] Breadcrumb tradotti
- [ ] Form tradotti
- [ ] Autori tradotti
- [ ] Verifica su `/en/` che tutto funzioni
- [ ] Cache pulita

## 🎉 Risultato Finale

Dopo aver completato tutte le traduzioni, il tuo sito sarà **completamente tradotto** in inglese quando gli utenti visitano `/en/`.

**Copertura finale: ~99%** degli elementi traducibili!








