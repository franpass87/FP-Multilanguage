# Elementi Traducibili - FP Multilanguage Plugin

**Data:** 19 Novembre 2025  
**Versione:** 0.9.6

## 📋 Riepilogo Elementi Traducibili

### ✅ Elementi Già Supportati

#### 1. Contenuti WordPress Core
- ✅ **Post/Articoli**: Titolo, contenuto, excerpt, slug
- ✅ **Pagine**: Titolo, contenuto, excerpt, slug
- ✅ **Custom Post Types**: Tutti i custom post types supportati
- ✅ **Categorie**: Nome, descrizione, slug
- ✅ **Tag**: Nome, descrizione, slug
- ✅ **Custom Taxonomies**: Tutte le taxonomie custom supportate

#### 2. Menu e Navigazione
- ✅ **Menu Items**: Titoli degli elementi menu
- ✅ **Menu Links**: Link corretti per entrambe le lingue

#### 3. Widget
- ✅ **Widget Titles**: Titoli dei widget
- ✅ **Widget Text**: Contenuto testuale dei widget (es. Text Widget)

#### 4. Media e Immagini
- ✅ **Featured Images**: Sincronizzazione automatica
- ⚠️ **Alt Text**: Non tradotto automaticamente (da aggiungere)
- ⚠️ **Captions**: Non tradotto automaticamente (da aggiungere)
- ⚠️ **Descriptions**: Non tradotto automaticamente (da aggiungere)

#### 5. Opzioni Tema
- ✅ **Salient Theme**: Header text, footer text, copyright, CTA
- ⚠️ **Customizer Options**: Non tradotto automaticamente (da aggiungere)
- ⚠️ **Theme Mods**: Non tradotto automaticamente (da aggiungere)

#### 6. Opzioni Plugin
- ✅ **WooCommerce**: Shop page title, cart title, checkout title
- ✅ **Contact Form 7**: Form titles
- ⚠️ **Altri Plugin**: Supporto generico via whitelist meta fields

#### 7. Meta Fields
- ✅ **Whitelist Meta Fields**: Meta fields traducibili configurati
- ✅ **Popular Plugins**: Elementor, Beaver Builder, Divi, ACF, Yoast SEO, etc.

#### 8. SEO
- ✅ **FP-SEO**: Meta title, description, keywords
- ✅ **Yoast SEO**: Title, meta description, focus keyword
- ✅ **Rank Math**: Title, description, Facebook title/description
- ✅ **All in One SEO**: Title, description, OG title/description

### ⚠️ Elementi Non Ancora Supportati (Da Aggiungere)

#### 1. Impostazioni WordPress Generali
- ❌ **Site Title** (`blogname`): Nome del sito
- ❌ **Tagline** (`blogdescription`): Descrizione del sito
- ❌ **Admin Email**: Email amministratore (probabilmente non necessario)
- ❌ **Timezone String**: Stringa timezone (probabilmente non necessario)

#### 2. Media e Immagini (Dettagli)
- ❌ **Image Alt Text**: Testo alternativo immagini
- ❌ **Image Captions**: Didascalie immagini
- ❌ **Image Descriptions**: Descrizioni immagini
- ❌ **Media Titles**: Titoli media

#### 3. Commenti
- ❌ **Comment Content**: Contenuto commenti
- ❌ **Comment Author Names**: Nomi autori commenti (probabilmente non necessario)

#### 4. Customizer
- ❌ **Customizer Options**: Opzioni personalizzatore
- ❌ **Theme Mods**: Modifiche tema
- ❌ **Widget Areas Names**: Nomi aree widget

#### 5. Form e Input
- ❌ **Form Labels**: Etichette form
- ❌ **Form Placeholders**: Placeholder form
- ❌ **Form Messages**: Messaggi form (success, error)
- ❌ **Button Texts**: Testi pulsanti

#### 6. Email e Notifiche
- ❌ **Email Templates**: Template email WordPress
- ❌ **Notification Messages**: Messaggi notifiche
- ❌ **Admin Notices**: Notifiche admin (probabilmente non necessario)

#### 7. User Meta
- ❌ **User Display Names**: Nomi visualizzati utenti (probabilmente non necessario)
- ❌ **User Descriptions**: Descrizioni utenti

#### 8. Altri Elementi
- ❌ **Post Excerpts**: Riassunti post (già supportato per post, ma potrebbe essere migliorato)
- ❌ **Archive Titles**: Titoli archivi
- ❌ **Search Results**: Messaggi risultati ricerca
- ❌ **404 Messages**: Messaggi errore 404
- ❌ **Breadcrumbs**: Testi breadcrumb

## 🎯 Priorità Implementazione

### Alta Priorità

1. **Site Title e Tagline**
   - Impatto: Alto (visibile ovunque)
   - Difficoltà: Bassa
   - Filtro: `option_blogname`, `option_blogdescription`

2. **Image Alt Text**
   - Impatto: Alto (SEO e accessibilità)
   - Difficoltà: Media
   - Meta: `_wp_attachment_image_alt`

3. **Image Captions**
   - Impatto: Medio (UX)
   - Difficoltà: Media
   - Campo: `post_excerpt` per attachment

### Media Priorità

4. **Customizer Options**
   - Impatto: Medio
   - Difficoltà: Media
   - Filtro: `theme_mod_{option_name}`

5. **Form Labels e Placeholders**
   - Impatto: Medio
   - Difficoltà: Alta (varia per plugin)
   - Dipende dal plugin form

6. **Comment Content**
   - Impatto: Basso (commenti utenti)
   - Difficoltà: Media
   - Campo: `comment_content`

### Bassa Priorità

7. **Email Templates**
   - Impatto: Basso (solo per utenti)
   - Difficoltà: Alta
   - Filtro: `wp_mail`

8. **Archive Titles**
   - Impatto: Basso
   - Difficoltà: Bassa
   - Filtro: `get_the_archive_title`

## 📝 Esempi Implementazione

### Esempio 1: Site Title e Tagline

```php
// In SiteTranslations.php, aggiungere:

add_filter( 'option_blogname', array( $this, 'filter_blogname' ), 10, 2 );
add_filter( 'option_blogdescription', array( $this, 'filter_blogdescription' ), 10, 2 );

public function filter_blogname( $value, $option ) {
    if ( ! $this->is_english() ) {
        return $value;
    }
    
    $translated = get_option( '_fpml_en_option_blogname' );
    return $translated ? $translated : $value;
}

public function filter_blogdescription( $value, $option ) {
    if ( ! $this->is_english() ) {
        return $value;
    }
    
    $translated = get_option( '_fpml_en_option_blogdescription' );
    return $translated ? $translated : $value;
}
```

### Esempio 2: Image Alt Text

```php
// In SiteTranslations.php, aggiungere:

add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_image_alt' ), 10, 3 );

public function filter_image_alt( $attr, $attachment, $size ) {
    if ( ! $this->is_english() ) {
        return $attr;
    }
    
    $translated_alt = get_post_meta( $attachment->ID, '_fpml_en_alt_text', true );
    if ( $translated_alt ) {
        $attr['alt'] = $translated_alt;
    }
    
    return $attr;
}
```

### Esempio 3: Image Captions

```php
// In SiteTranslations.php, aggiungere:

add_filter( 'wp_get_attachment_caption', array( $this, 'filter_image_caption' ), 10, 2 );

public function filter_image_caption( $caption, $post_id ) {
    if ( ! $this->is_english() || empty( $caption ) ) {
        return $caption;
    }
    
    $translated = get_post_meta( $post_id, '_fpml_en_caption', true );
    return $translated ? $translated : $caption;
}
```

### Esempio 4: Customizer Options

```php
// In SiteTranslations.php, aggiungere:

add_filter( 'theme_mod_{option_name}', array( $this, 'filter_theme_mod' ), 10, 2 );

public function filter_theme_mod( $value, $name ) {
    if ( ! $this->is_english() ) {
        return $value;
    }
    
    $translated = get_option( '_fpml_en_theme_mod_' . $name );
    return $translated ? $translated : $value;
}
```

### Esempio 5: Comment Content

```php
// In SiteTranslations.php, aggiungere:

add_filter( 'get_comment_text', array( $this, 'filter_comment_text' ), 10, 3 );

public function filter_comment_text( $content, $comment, $args ) {
    if ( ! $this->is_english() || empty( $content ) ) {
        return $content;
    }
    
    $translated = get_comment_meta( $comment->comment_ID, '_fpml_en_content', true );
    return $translated ? $translated : $content;
}
```

## 🔧 Estendere SitePartTranslator

Per aggiungere traduzione di nuovi elementi in `SitePartTranslator.php`:

```php
// Aggiungere nuovo case
case 'site-settings':
    return $this->translate_site_settings();
case 'media':
    return $this->translate_media();
case 'comments':
    return $this->translate_comments();

// Implementare metodi
private function translate_site_settings() {
    $translated_count = 0;
    
    // Site title
    $site_title = get_option( 'blogname' );
    if ( $site_title ) {
        $translated = $this->translate_text( $site_title );
        if ( $translated ) {
            update_option( '_fpml_en_option_blogname', $translated );
            $translated_count++;
        }
    }
    
    // Tagline
    $tagline = get_option( 'blogdescription' );
    if ( $tagline ) {
        $translated = $this->translate_text( $tagline );
        if ( $translated ) {
            update_option( '_fpml_en_option_blogdescription', $translated );
            $translated_count++;
        }
    }
    
    return array(
        'message' => sprintf( __( '%d impostazioni sito tradotte.', 'fp-multilanguage' ), $translated_count ),
        'count' => $translated_count,
    );
}
```

## 📊 Statistiche Supporto Attuale

| Categoria | Elementi | Supportati | % |
|-----------|----------|------------|---|
| **Contenuti Core** | 6 | 6 | 100% |
| **Menu** | 2 | 2 | 100% |
| **Widget** | 2 | 2 | 100% |
| **Media** | 4 | 1 | 25% |
| **Opzioni Tema** | 3 | 1 | 33% |
| **Opzioni Plugin** | 10+ | 5+ | ~50% |
| **SEO** | 4 | 4 | 100% |
| **Impostazioni Generali** | 2 | 0 | 0% |
| **Commenti** | 1 | 0 | 0% |
| **Form** | 3 | 0 | 0% |
| **Customizer** | 2 | 0 | 0% |

**Totale Supporto**: ~60% degli elementi principali

## ✅ Raccomandazioni Implementazione

### Fase 1 (Alta Priorità)
1. ✅ Site Title e Tagline
2. ✅ Image Alt Text
3. ✅ Image Captions

### Fase 2 (Media Priorità)
4. ✅ Customizer Options
5. ✅ Form Labels (per plugin comuni)
6. ✅ Comment Content

### Fase 3 (Bassa Priorità)
7. ⚠️ Email Templates
8. ⚠️ Archive Titles
9. ⚠️ 404 Messages

## 🎯 Conclusione

Il plugin supporta già **la maggior parte degli elementi principali** (post, pagine, categorie, menu, widget, SEO). Gli elementi mancanti più importanti sono:

1. **Site Title e Tagline** - Facile da implementare, alto impatto
2. **Image Alt Text e Captions** - Importante per SEO e accessibilità
3. **Customizer Options** - Utile per temi personalizzati

Tutti questi possono essere facilmente aggiunti seguendo il pattern esistente in `SiteTranslations.php` e `SitePartTranslator.php`.








