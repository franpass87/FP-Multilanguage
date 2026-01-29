# Comment Translation

**Versione**: 0.9.1+  
**File**: `src/Core/Plugin.php`

---

## 📋 Panoramica

FP Multilanguage supporta la traduzione automatica dei commenti WordPress, inclusi i commenti annidati (threaded comments).

### ✅ Funzionalità

- ✅ **Traduzione automatica** dei commenti quando vengono pubblicati
- ✅ **Commenti annidati** - Supporto completo per commenti con parent
- ✅ **Mapping parent/child** - Mantiene la gerarchia dei commenti tra lingue
- ✅ **Queue-based translation** - Contenuto commento tradotto tramite queue
- ✅ **Validazione parent** - Verifica che il commento parent esista nel post tradotto
- ✅ **Meta relationships** - Usa `_fpml_pair_id` per mappare commenti IT ↔ EN

---

## 🔄 Come Funziona

### 1. Pubblicazione Commento

Quando un commento viene pubblicato su un post italiano:

```php
// Hook: comment_post
add_action( 'comment_post', 'handle_comment_post', 10, 3 );
```

**Processo**:
1. Verifica che il post abbia una traduzione EN
2. Controlla se il commento ha un parent
3. Se ha parent, trova la traduzione del parent comment
4. Crea il commento tradotto con `comment_parent` corretto
5. Accoda job per tradurre il contenuto del commento

### 2. Gestione Commenti Annidati

```php
// Gestisci commenti annidati: trova la traduzione del commento parent
$comment_parent = 0;
if ( ! empty( $comment->comment_parent ) && $comment->comment_parent > 0 ) {
    $parent_comment_id = (int) $comment->comment_parent;
    $parent_translation_id = (int) get_comment_meta( $parent_comment_id, '_fpml_pair_id', true );
    
    if ( $parent_translation_id > 0 ) {
        // Verifica che il commento tradotto esista ancora
        $parent_translation = get_comment( $parent_translation_id );
        if ( $parent_translation && (int) $parent_translation->comment_post_ID === $target_post_id ) {
            $comment_parent = $parent_translation_id;
        }
    }
}
```

**Validazioni**:
- Verifica che `comment_parent` esista
- Controlla che la traduzione del parent esista (`_fpml_pair_id`)
- Valida che il parent tradotto appartenga al post tradotto corretto
- Imposta `comment_parent` solo se tutte le validazioni passano

### 3. Meta Fields

**Comment Meta salvati**:
- `_fpml_is_translation` - Flag che indica se è una traduzione
- `_fpml_pair_source_id` - ID del commento originale
- `_fpml_pair_id` - ID del commento tradotto (salvato sul commento originale)

---

## 📝 Esempio d'Uso

### Scenario: Commenti Annidati

**Post IT** (ID: 100):
- Commento 1: "Ottimo articolo!" (parent: 0)
- Commento 2: "Grazie!" (parent: 1) ← Risposta a Commento 1

**Post EN** (ID: 200) - Tradotto automaticamente:
- Commento 1: "Great article!" (parent: 0)
- Commento 2: "Thank you!" (parent: 1) ← Risposta a Commento 1 EN

La gerarchia viene preservata correttamente tra le lingue.

---

## 🔌 Hook Disponibili

### Actions

Nessun hook specifico per i commenti al momento, ma puoi usare:

```php
// Dopo che un commento viene tradotto
add_action( 'comment_post', function( $comment_id, $approved ) {
    $translated_id = get_comment_meta( $comment_id, '_fpml_pair_id', true );
    if ( $translated_id ) {
        // Commento tradotto creato
    }
}, 20, 2 );
```

---

## ⚙️ Configurazione

La traduzione dei commenti è **automatica** e non richiede configurazione aggiuntiva.

**Requisiti**:
- Il post deve avere una traduzione EN esistente
- OpenAI API key deve essere configurata
- Queue deve essere attiva (WP-Cron o manuale)

---

## 🐛 Troubleshooting

### Commenti non vengono tradotti

**Causa**: Post non ha traduzione EN
**Soluzione**: Traduci prima il post, poi i commenti verranno tradotti automaticamente

### Gerarchia commenti persa

**Causa**: Parent comment non tradotto
**Soluzione**: Assicurati che il parent comment sia stato tradotto prima del child

### Commento parent errato

**Causa**: Validazione fallita
**Soluzione**: Il sistema verifica automaticamente che il parent appartenga al post corretto

---

## 📊 Coverage

- **Commenti semplici**: 100%
- **Commenti annidati**: 100%
- **Gerarchia multi-livello**: Supportata (unlimited depth)
- **Validazione parent**: Completa

---

**Ultimo aggiornamento**: Novembre 2025  
**Versione**: 0.9.1+







