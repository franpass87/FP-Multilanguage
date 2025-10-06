# Code Examples - FP Multilanguage

## Overview

Questa directory contiene esempi pratici di integrazione e personalizzazione del plugin FP Multilanguage.

---

## File Disponibili

### 1. `woocommerce-integration.php`
Esempi completi di integrazione WooCommerce:
- ✅ Traduzione attributi prodotto
- ✅ Sync categorie prodotto
- ✅ Descrizioni brevi
- ✅ Variazioni prodotto
- ✅ Prezzi localizzati
- ✅ Recensioni tradotte
- ✅ Sync stock tra versioni

**Uso:**
```php
// In functions.php del tema
require_once get_template_directory() . '/fpml-woocommerce.php';
```

---

### 2. `custom-post-types.php`
Integrazione con Custom Post Types:
- ✅ Registrazione e traduzione CPT
- ✅ Campi custom ACF
- ✅ Tassonomie custom
- ✅ Repeater fields
- ✅ Eventi con date localizzate
- ✅ Portfolio items
- ✅ Ricette con ingredienti

**Uso:**
```php
// Copia esempi specifici nel tuo tema/plugin
```

---

### 3. `advanced-hooks.php`
Hooks avanzati e pattern:
- ✅ Quality score per traduzioni
- ✅ A/B testing traduzioni
- ✅ Workflow con approvazione
- ✅ Review interface admin
- ✅ Post-processing personalizzato

**Uso:**
```php
// Scegli gli esempi che ti servono e adattali
```

---

## Quick Examples

### Esempio Rapido 1: Salta Traduzioni Vecchie

```php
// In functions.php
add_action( 'save_post', function( $post_id, $post ) {
	// Non tradurre post più vecchi di 2 anni
	$post_date = strtotime( $post->post_date );
	$two_years_ago = strtotime( '-2 years' );
	
	if ( $post_date < $two_years_ago ) {
		update_post_meta( $post_id, '_fpml_skip', 1 );
	}
}, 5, 2 );

add_filter( 'fpml_translatable_post_types', function( $types ) {
	// Keep all types
	return $types;
});

add_filter( 'fpml_should_translate_post', function( $should, $post ) {
	if ( get_post_meta( $post->ID, '_fpml_skip', true ) ) {
		return false;
	}
	return $should;
}, 10, 2 );
```

---

### Esempio Rapido 2: Traduzioni Prioritarie

```php
// Homepage posts first
add_filter( 'fpml_queue_order', function( $orderby ) {
	global $wpdb;
	$table = $wpdb->prefix . 'fpml_queue';
	
	return "
		CASE 
			WHEN object_id IN (
				SELECT ID FROM {$wpdb->posts} 
				WHERE post_status = 'publish' 
				AND post_type = 'post'
				AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_is_homepage_post')
			) THEN 1
			ELSE 2
		END ASC,
		created_at ASC
	";
});
```

---

### Esempio Rapido 3: Notifica Slack Errori

```php
add_action( 'fpml_queue_batch_complete', function( $summary ) {
	$errors = isset( $summary['errors'] ) ? (int) $summary['errors'] : 0;
	
	if ( $errors === 0 ) {
		return; // No errors, no notification
	}

	// Send to Slack
	$webhook_url = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
	
	wp_remote_post( $webhook_url, array(
		'body' => json_encode( array(
			'text' => "⚠️ Translation batch completed with $errors errors!",
			'attachments' => array(
				array(
					'color' => 'danger',
					'fields' => array(
						array( 'title' => 'Processed', 'value' => $summary['processed'], 'short' => true ),
						array( 'title' => 'Errors', 'value' => $errors, 'short' => true ),
					),
				),
			),
		)),
		'headers' => array( 'Content-Type' => 'application/json' ),
	));
});
```

---

### Esempio Rapido 4: Cache Translation Glossary

```php
add_filter( 'fpml_glossary_pre_translate', function( $text, $source, $target ) {
	// Check cache
	$cache_key = 'fpml_glossary_' . md5( $text );
	$cached = wp_cache_get( $cache_key, 'fpml' );
	
	if ( false !== $cached ) {
		return $cached;
	}
	
	// Apply glossary (original logic)
	$processed = apply_glossary_rules( $text );
	
	// Cache for 1 day
	wp_cache_set( $cache_key, $processed, 'fpml', DAY_IN_SECONDS );
	
	return $processed;
}, 10, 3 );
```

---

### Esempio Rapido 5: Auto-Publish Traduzioni

```php
// Auto-publish high-confidence translations
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
	// Only for post_content
	if ( 'post_content' !== $field ) {
		return;
	}

	// Check quality score
	$quality = get_post_meta( $post_id, '_fpml_quality_score', true );
	
	// If high quality, auto-publish
	if ( $quality >= 0.9 ) {
		wp_update_post( array(
			'ID' => $post_id,
			'post_status' => 'publish',
		));
		
		update_post_meta( $post_id, '_fpml_auto_published', 1 );
	}
}, 10, 3 );
```

---

## Testing Examples

### Test nel tuo ambiente

```bash
# 1. Copia esempio
cp docs/examples/woocommerce-integration.php \
   wp-content/mu-plugins/fpml-custom.php

# 2. Test
wp eval "
require_once WPMU_PLUGIN_DIR . '/fpml-custom.php';
echo 'Custom code loaded';
"

# 3. Verifica
wp fpml queue run --batch=5
```

---

## Contributing Examples

Hai un esempio utile? Contribuisci!

1. Fork repository
2. Aggiungi esempio in `docs/examples/`
3. Documenta uso e benefici
4. Submit pull request

---

## Support

Per domande sugli esempi:
- **Issues:** https://github.com/francescopasseri/FP-Multilanguage/issues
- **Email:** info@francescopasseri.com

---

**Last updated:** 2025-10-05  
**Plugin version:** 0.3.2
