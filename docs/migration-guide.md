# Migration Guide - FP Multilanguage

## Migrare da Altri Plugin

Guida completa per migrare a FP Multilanguage da altri plugin di traduzione.

---

## Da WPML

### Pre-Migration Checklist

```bash
# 1. Backup completo
wp db export wpml-backup-$(date +%Y%m%d).sql

# 2. Export configurazione WPML
wp eval "
\$settings = get_option('icl_sitepress_settings');
file_put_contents('wpml-settings.json', json_encode(\$settings, JSON_PRETTY_PRINT));
"

# 3. Count traduzioni esistenti
wp db query "SELECT COUNT(*) FROM wp_icl_translations;"
```

---

### Step 1: Installa FP Multilanguage

```bash
# Mantieni WPML attivo durante migration
wp plugin install fp-multilanguage --activate

# Plugin entra in "assisted mode" automaticamente
wp fpml queue status
# Output: "Modalità assistita attiva (WPML)"
```

---

### Step 2: Mapping Content

```php
/**
 * Map WPML translations to FPML structure.
 */
function fpml_migrate_from_wpml() {
    global $wpdb;
    
    // Get all WPML translation pairs (IT → EN)
    $translations = $wpdb->get_results( "
        SELECT 
            t1.element_id as it_id,
            t2.element_id as en_id
        FROM {$wpdb->prefix}icl_translations t1
        JOIN {$wpdb->prefix}icl_translations t2 
            ON t1.trid = t2.trid
        WHERE t1.language_code = 'it'
        AND t2.language_code = 'en'
        AND t1.element_type LIKE 'post_%'
    " );
    
    $migrated = 0;
    
    foreach ( $translations as $pair ) {
        // Create FPML link
        update_post_meta( $pair->it_id, '_fpml_pair_id', $pair->en_id );
        update_post_meta( $pair->en_id, '_fpml_pair_source_id', $pair->it_id );
        update_post_meta( $pair->en_id, '_fpml_is_translation', 1 );
        
        $migrated++;
    }
    
    return $migrated;
}

// Run migration
add_action( 'admin_init', function() {
    if ( isset( $_GET['fpml_migrate_wpml'] ) && current_user_can( 'manage_options' ) ) {
        $count = fpml_migrate_from_wpml();
        
        wp_redirect( admin_url( 'admin.php?page=fpml-settings&migrated=' . $count ) );
        exit;
    }
});
```

**Execute:**
```bash
wp eval "echo fpml_migrate_from_wpml() . ' posts migrated';"
```

---

### Step 3: Migrate Taxonomy Terms

```php
function fpml_migrate_wpml_terms() {
    global $wpdb;
    
    $term_translations = $wpdb->get_results( "
        SELECT 
            t1.element_id as it_id,
            t2.element_id as en_id
        FROM {$wpdb->prefix}icl_translations t1
        JOIN {$wpdb->prefix}icl_translations t2 
            ON t1.trid = t2.trid
        WHERE t1.language_code = 'it'
        AND t2.language_code = 'en'
        AND t1.element_type LIKE 'tax_%'
    " );
    
    $migrated = 0;
    
    foreach ( $term_translations as $pair ) {
        update_term_meta( $pair->it_id, '_fpml_pair_id', $pair->en_id );
        update_term_meta( $pair->en_id, '_fpml_pair_source_id', $pair->it_id );
        update_term_meta( $pair->en_id, '_fpml_is_translation', 1 );
        
        $migrated++;
    }
    
    return $migrated;
}
```

---

### Step 4: Deactivate WPML

```bash
# Dopo aver verificato la migrazione
wp plugin deactivate sitepress-multilingual-cms

# Verifica che FP Multilanguage esca da assisted mode
wp fpml queue status
# Non dovrebbe più mostrare "assisted mode"

# Flush rewrite rules
wp rewrite flush
```

---

### Step 5: Cleanup WPML Data (Opzionale)

```bash
# ⚠️  ATTENZIONE: Esegui solo dopo backup e verifica completa!

# Remove WPML tables
wp db query "DROP TABLE IF EXISTS wp_icl_translations;"
wp db query "DROP TABLE IF EXISTS wp_icl_languages;"
wp db query "DROP TABLE IF EXISTS wp_icl_strings;"

# Remove WPML options
wp option delete icl_sitepress_settings
wp option delete wpml_options

# Cleanup postmeta
wp db query "DELETE FROM wp_postmeta WHERE meta_key LIKE '_icl_%';"
```

---

## Da Polylang

### Pre-Migration

```bash
# Backup
wp db export polylang-backup-$(date +%Y%m%d).sql

# Count traduzioni
wp db query "
SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT term_taxonomy_id) as languages
FROM wp_term_relationships
WHERE term_taxonomy_id IN (
    SELECT term_taxonomy_id 
    FROM wp_term_taxonomy 
    WHERE taxonomy = 'language'
);"
```

---

### Migration Script

```php
function fpml_migrate_from_polylang() {
    if ( ! function_exists( 'pll_get_post_translations' ) ) {
        return new WP_Error( 'no_polylang', 'Polylang not active' );
    }
    
    $migrated = 0;
    
    // Get all Italian posts
    $posts = get_posts( array(
        'lang' => 'it',
        'post_type' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ));
    
    foreach ( $posts as $it_post_id ) {
        // Get English translation
        $en_post_id = pll_get_post( $it_post_id, 'en' );
        
        if ( ! $en_post_id ) {
            continue;
        }
        
        // Create FPML links
        update_post_meta( $it_post_id, '_fpml_pair_id', $en_post_id );
        update_post_meta( $en_post_id, '_fpml_pair_source_id', $it_post_id );
        update_post_meta( $en_post_id, '_fpml_is_translation', 1 );
        
        $migrated++;
    }
    
    // Migrate terms
    $terms = get_terms( array(
        'lang' => 'it',
        'taxonomy' => 'any',
        'hide_empty' => false,
        'fields' => 'ids',
    ));
    
    foreach ( $terms as $it_term_id ) {
        $en_term_id = pll_get_term( $it_term_id, 'en' );
        
        if ( ! $en_term_id ) {
            continue;
        }
        
        update_term_meta( $it_term_id, '_fpml_pair_id', $en_term_id );
        update_term_meta( $en_term_id, '_fpml_pair_source_id', $it_term_id );
        update_term_meta( $en_term_id, '_fpml_is_translation', 1 );
        
        $migrated++;
    }
    
    return $migrated;
}
```

**Execute:**
```bash
wp eval "echo fpml_migrate_from_polylang() . ' items migrated';"
```

---

## Da TranslatePress

### Migration Strategy

TranslatePress usa approccio diverso (on-the-fly), quindi serve creare copie English:

```php
function fpml_migrate_from_translatepress() {
    if ( ! class_exists( 'TRP_Translate_Press' ) ) {
        return new WP_Error( 'no_translatepress', 'TranslatePress not active' );
    }
    
    global $wpdb;
    
    // TranslatePress store translations in custom tables
    $tp_table = $wpdb->prefix . 'trp_dictionary_en_us_it_it';
    
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$tp_table'" ) ) {
        return new WP_Error( 'no_tp_table', 'TranslatePress table not found' );
    }
    
    $migrated = 0;
    
    // Get all posts
    $posts = get_posts( array(
        'post_type' => 'any',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ));
    
    foreach ( $posts as $post ) {
        // Check if has translations
        $has_translation = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $tp_table WHERE original LIKE %s",
            '%' . $wpdb->esc_like( $post->post_title ) . '%'
        ));
        
        if ( ! $has_translation ) {
            continue;
        }
        
        // Create English copy with FP Multilanguage
        $plugin = FPML_Plugin::instance();
        $target = $plugin->ensure_post_translation( $post );
        
        if ( $target ) {
            // Get translated content from TP
            $title_tr = $wpdb->get_var( $wpdb->prepare(
                "SELECT translated FROM $tp_table WHERE original = %s AND status = %d",
                $post->post_title,
                2
            ));
            
            if ( $title_tr ) {
                wp_update_post( array(
                    'ID' => $target->ID,
                    'post_title' => $title_tr,
                ));
            }
            
            $migrated++;
        }
    }
    
    return $migrated;
}
```

---

## Da Weglot

### API-Based Migration

```php
function fpml_migrate_from_weglot() {
    $api_key = get_option( 'weglot_api_key' );
    
    if ( ! $api_key ) {
        return new WP_Error( 'no_weglot_key', 'Weglot API key not found' );
    }
    
    $posts = get_posts( array(
        'post_type' => 'any',
        'posts_per_page' => 100,
        'post_status' => 'publish',
    ));
    
    $migrated = 0;
    
    foreach ( $posts as $post ) {
        // Get translations from Weglot API
        $response = wp_remote_post( 'https://api.weglot.com/translate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( array(
                'l_from' => 'it',
                'l_to' => 'en',
                'request_url' => get_permalink( $post ),
                'words' => array(
                    array( 't' => 1, 'w' => $post->post_title ),
                    array( 't' => 1, 'w' => $post->post_content ),
                ),
            )),
        ));
        
        if ( is_wp_error( $response ) ) {
            continue;
        }
        
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        // Create FPML translation with Weglot data
        // Implementation depends on Weglot response structure
        
        $migrated++;
    }
    
    return $migrated;
}
```

---

## Post-Migration Verification

### Checklist

```bash
# 1. Verify translation pairs
wp eval "
\$posts = get_posts(['posts_per_page' => 10]);
foreach (\$posts as \$post) {
    \$pair = get_post_meta(\$post->ID, '_fpml_pair_id', true);
    echo \"Post #{\$post->ID}: Pair #\$pair\n\";
}
"

# 2. Test language switching
curl -I https://example.com/en/
# Should return 200

# 3. Verify queue working
wp fpml queue run --batch=5

# 4. Check for broken links
wp db query "
SELECT ID, post_title 
FROM wp_posts 
WHERE post_status = 'publish'
AND ID NOT IN (
    SELECT DISTINCT meta_value 
    FROM wp_postmeta 
    WHERE meta_key = '_fpml_pair_id'
    AND meta_value != ''
)
LIMIT 10;
"

# 5. Test frontend
# Visit: https://example.com/
# Visit: https://example.com/en/
```

---

## Troubleshooting Migration

### Issue: Missing Translations

**Diagnose:**
```bash
wp db query "
SELECT COUNT(*) as missing
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_fpml_pair_id'
WHERE p.post_status = 'publish'
AND p.post_type IN ('post', 'page')
AND pm.meta_id IS NULL;
"
```

**Fix:**
```bash
# Re-index to create missing translations
wp eval "print_r(FPML_Plugin::instance()->reindex_content());"
```

---

### Issue: Duplicate Content

**Diagnose:**
```bash
# Check for duplicate slugs
wp db query "
SELECT post_name, COUNT(*) as count
FROM wp_posts
WHERE post_status = 'publish'
GROUP BY post_name
HAVING count > 1;
"
```

**Fix:**
```php
// Auto-fix duplicate slugs
function fpml_fix_duplicate_slugs() {
    $duplicates = $wpdb->get_results( "..." ); // Query above
    
    foreach ( $duplicates as $dup ) {
        $posts = get_posts( array(
            'name' => $dup->post_name,
            'post_type' => 'any',
            'posts_per_page' => -1,
        ));
        
        // Keep first, rename others
        foreach ( array_slice( $posts, 1 ) as $post ) {
            wp_update_post( array(
                'ID' => $post->ID,
                'post_name' => $post->post_name . '-en-' . $post->ID,
            ));
        }
    }
}
```

---

## Rollback Plan

Se la migrazione fallisce:

```bash
# 1. Deactivate FP Multilanguage
wp plugin deactivate fp-multilanguage

# 2. Restore WPML backup
wp db import wpml-backup-YYYYMMDD.sql

# 3. Reactivate WPML
wp plugin activate sitepress-multilingual-cms

# 4. Flush caches
wp cache flush
wp rewrite flush
```

---

## Gradual Migration (Recommended)

### Strategia Canary

1. **Week 1:** Install FP Multilanguage (assisted mode)
   - Test su 10% contenuto nuovo
   - Lascia WPML gestire esistente

2. **Week 2:** Migra contenuto critico
   - Homepage
   - Top 10 pages
   - Verifica qualità traduzioni

3. **Week 3:** Migra resto contenuto
   - Batch migration
   - Monitor error rate

4. **Week 4:** Switch completo
   - Deactivate WPML
   - FP Multilanguage full control

---

## Feature Comparison

| Feature | WPML | Polylang | FP Multilanguage |
|---------|------|----------|------------------|
| **Auto Translation** | ❌ No | ❌ No | ✅ Yes (AI) |
| **Cost** | €99/year | €99/year | ✅ Free |
| **Queue System** | ❌ No | ❌ No | ✅ Yes |
| **API Transparency** | ❌ No | ❌ No | ✅ Yes (4 providers) |
| **WP-CLI** | ✅ Yes | ✅ Yes | ✅ Yes (enhanced) |
| **REST API** | ✅ Yes | ⚠️ Limited | ✅ Yes |
| **Cost Tracking** | ❌ No | ❌ No | ✅ Yes |
| **Health Monitoring** | ❌ No | ❌ No | ✅ Yes |
| **Webhooks** | ❌ No | ❌ No | ✅ Yes |

---

## FAQ Migration

### Q: Posso mantenere entrambi attivi?
**A:** Sì, FP Multilanguage entra in "assisted mode" e disabilita auto-duplication per evitare conflitti.

### Q: Perdo le traduzioni esistenti?
**A:** No, con gli script di migration preservi tutto. Raccomandato backup comunque.

### Q: Quanto tempo richiede?
**A:** 
- Setup: 30 minuti
- Migration 1000 posts: 2-3 ore
- Testing: 1-2 giorni
- Total: 3-5 giorni per sito medio

### Q: Posso tornare indietro?
**A:** Sì, se hai backup completo. Restore database + riattiva vecchio plugin.

---

## Support

Per assistenza migration:
- **Email:** migration@francescopasseri.com
- **Issues:** https://github.com/francescopasseri/FP-Multilanguage/issues
- **Consultation:** https://francescopasseri.com/contact

---

**Last updated:** 2025-10-05  
**Plugin version:** 0.3.2
