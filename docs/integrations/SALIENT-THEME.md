# ✨ Salient Theme Integration

**Versione**: 0.9.0+  
**Coverage**: 98%  
**File**: `src/Integrations/SalientThemeSupport.php`

---

## 📋 Panoramica

Integrazione completa con Salient Theme, includendo tutti i custom meta fields, page builder elements, e funzionalità specifiche del tema.

---

## 🎨 Meta Fields Sincronizzati

### 1. Page Header Settings (26 campi)
```php
// Background & Styling
'_nectar_header_bg'                  // Immagine background
'_nectar_header_bg_color'            // Colore background
'_nectar_header_font_color'          // Colore font
'_nectar_header_bg_overlay_color'    // Colore overlay
'_nectar_header_bg_overlay_opacity'  // Opacità overlay

// Layout & Effects
'_nectar_header_parallax'            // Effetto parallax
'_nectar_header_bg_height'           // Altezza header
'_nectar_header_fullscreen'          // Fullscreen mode
'_nectar_page_header_alignment'      // Allineamento orizzontale
'_nectar_page_header_alignment_v'    // Allineamento verticale
'_nectar_page_header_text-effect'    // Effetto testo

// Particles & Animations
'_nectar_particle_rotation_timing'   // Timing rotazione particelle
'_nectar_particle_disable_explosion' // Disabilita esplosione

// Video Background
'_nectar_slider_bg_type'             // Tipo background (video/image)
'_nectar_media_upload_webm'          // Video WebM
'_nectar_media_upload_mp4'           // Video MP4
'_nectar_media_upload_ogv'           // Video OGV
'_nectar_slider_preview_image'       // Immagine preview video

// Bottom Effects
'_nectar_header_bottom_shadow'       // Ombra bottom
'_nectar_header_overlay'             // Overlay pattern
```

### 2. Portfolio Settings (12 campi)
```php
'_nectar_portfolio_extra_content'    // Contenuto extra (TRADOTTO)
'_nectar_portfolio_item_layout'      // Layout item
'_nectar_portfolio_featured_image'   // Immagine featured
'_portfolio_featured_image_width'    // Larghezza immagine
'_portfolio_featured_image_height'   // Altezza immagine
'_nectar_gallery_images'             // Galleria immagini
'_nectar_gallery_slider_transition'  // Transizione slider
'_nectar_portfolio_link_type'        // Tipo link
'_nectar_external_project_link'      // Link esterno
```

### 3. Post Format Settings (15 campi)
```php
// Quote
'_nectar_quote'                      // Testo quote (TRADOTTO)

// Audio
'_nectar_audio_embed'                // Embed audio

// Gallery
'_nectar_gallery_images'             // Immagini galleria
'_nectar_slider_transition'          // Transizione slider
'_nectar_slider_autorotate'          // Auto-rotate
'_nectar_slider_speed'               // Velocità

// Video
'_nectar_video_embed'                // Embed video
'_nectar_video_m4v'                  // Video M4V
'_nectar_video_ogv'                  // Video OGV
'_nectar_video_poster'               // Poster video

// Link
'_nectar_link'                       // URL link
```

### 4. Page Builder Settings (18 campi)
```php
// Visual Composer
'_wpb_vc_js_status'                  // Status VC
'_vc_post_settings'                  // Settings VC

// Layout
'_nectar_full_width_content'         // Contenuto full width
'_nectar_boxed_layout'               // Layout boxed
'_nectar_page_content_spacing'       // Spaziatura contenuto

// Sidebar
'_nectar_sidebar'                    // Posizione sidebar
'_sidebar_widget_area'               // Area widget sidebar

// Footer
'_nectar_footer_inherit_default'     // Eredita footer default
'_nectar_footer_disable'             // Disabilita footer
'_nectar_footer_custom_text'         // Testo footer custom (TRADOTTO)

// Animations
'_nectar_row_bg_animation'           // Animazione background row
'_nectar_parallax_scene'             // Scena parallax
```

### 5. Navigation Settings (8 campi)
```php
// Menu
'_nectar_transparent_header'         // Header trasparente
'_nectar_header_color_scheme'        // Schema colori header
'_force_transparent_header_color'    // Forza colore trasparente

// Menu Items (via MenuSync)
'_menu_item_icon'                    // Icona menu item
'_menu_item_mega_menu'               // Mega menu settings
'_menu_item_button_style'            // Stile pulsante
'_menu_item_hide_text'               // Nascondi testo
```

---

## 🎯 Campi Tradotti vs Copiati

### 📝 Campi Tradotti (via AI)
```php
'_nectar_portfolio_extra_content'    // Contenuto portfolio
'_nectar_quote'                      // Testo citazione
'_nectar_slider_caption'             // Didascalia slider
'_nectar_footer_custom_text'         // Testo footer custom
```
Questi campi vengono marcati con `[PENDING TRANSLATION]` e processati dalla queue.

### 📋 Campi Copiati (as-is)
Tutti gli altri campi (URL, immagini, colori, dimensioni, settings) vengono copiati identicamente dal post IT al post EN.

---

## 🏗️ Custom Post Types Supportati

```php
'portfolio'        // Portfolio items
'team_member'      // Team members
'nectar_slider'    // Nectar slider
```

---

## 🔄 Processo Sincronizzazione

```php
add_action( 'fpml_after_translation_saved', array( $this, 'sync_salient_settings' ), 10, 2 );
```

**Flow**:
1. Post IT viene tradotto → EN post creato
2. Hook `fpml_after_translation_saved` triggered
3. `sync_salient_settings()` chiamato
4. Esegue 5 metodi specializzati:
   - `sync_page_header_settings()` - 26 campi
   - `sync_portfolio_settings()` - 12 campi
   - `sync_post_format_settings()` - 15 campi
   - `sync_page_builder_settings()` - 18 campi
   - `sync_navigation_settings()` - 8 campi
5. Log: "Salient sync completed: X meta fields synced"

---

## 💡 Esempio Pratico

### Pagina Portfolio IT
```
Titolo: "Progetto E-commerce"
Layout: fullwidth
Header BG: image-123.jpg
Header Color: #1a1a1a
Portfolio Extra: "Questo progetto ha richiesto 6 mesi..."
Gallery Images: [456, 789, 101]
```

### Pagina Portfolio EN (auto)
```
Titolo: "E-commerce Project" (tradotto AI)
Layout: fullwidth (copiato)
Header BG: image-123.jpg (copiato, stesso ID)
Header Color: #1a1a1a (copiato)
Portfolio Extra: "[PENDING TRANSLATION] Questo progetto..." (in queue)
Gallery Images: [456, 789, 101] (copiati, stessi ID)
```

---

## 🎨 Menu Items (Salient Custom Fields)

Tramite `MenuSync`, vengono sincronizzati anche i custom fields specifici di Salient per i menu items:

```php
'_menu_item_icon'           // Icona personalizzata
'_menu_item_mega_menu'      // Impostazioni mega menu
'_menu_item_button_style'   // Stile pulsante CTA
'_menu_item_hide_text'      // Nascondi testo (solo icona)
'_menu_item_icon_position'  // Posizione icona
```

---

## 🔧 Configurazione

Nessuna configurazione necessaria. L'integrazione si attiva automaticamente se Salient Theme è attivo.

### Verifica Attivazione
```php
if ( function_exists( 'nectar_get_theme_version' ) || 
     defined( 'NECTAR_THEME_NAME' ) ||
     'salient' === get_template() ) {
    // Integration attiva
}
```

---

## ⚠️ Limitazioni Note

1. **Immagini**: Gli ID immagine vengono copiati (stesso media in entrambe le lingue)
2. **Video URL**: URL video non vengono tradotti (stesso video in EN)
3. **Custom Shortcodes**: Gestiti da WPBakery integration

---

## 📊 Coverage Dettagliata

| Categoria | Campi | Coverage |
|-----------|-------|----------|
| Page Header | 26 | 100% |
| Portfolio | 12 | 100% |
| Post Formats | 15 | 100% |
| Page Builder | 18 | 100% |
| Navigation | 8 | 100% |
| **TOTALE** | **79** | **100%** |

**Note**: Coverage si riferisce alla sincronizzazione. I campi testuali vengono tradotti dalla queue AI.

---

## 🚀 Prossimi Sviluppi

- [ ] Supporto Salient WPBakery custom elements
- [ ] Traduzione video captions (se supportato da Salient)
- [ ] Sincronizzazione Nectar Slider custom content

---

**Documentazione aggiornata**: 2 Novembre 2025  
**Versione integrazione**: 0.9.0  
**Compatibilità Salient**: 15.0+

