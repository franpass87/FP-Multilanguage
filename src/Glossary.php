<?php
/**
 * Glossary manager handling custom terminology enforcement.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Provide storage and helpers for glossary entries.
 *
 * @since 0.2.0
 */
class Glossary {
	use ContainerAwareTrait;
        /**
         * Option name storing glossary entries.
         */
        const OPTION_KEY = '\FPML_glossary_entries';

        /**
         * Singleton instance.
         *
         * @var \FPML_Glossary|null
         */
        protected static $instance = null;

        /**
         * Cached glossary entries.
         *
         * @var array
         */
        protected $entries = array();

        /**
         * Constructor.
         *
         * @since 0.2.0
         * @since 1.0.0 Now public to support dependency injection
         */
        public function __construct() {
                $this->entries = $this->load_entries();
                $this->register_filters();
        }

        /**
         * Retrieve singleton instance (for backward compatibility).
         *
         * @since 0.2.0
         * @deprecated 1.0.0 Use dependency injection via container instead
         *
         * @return \FPML_Glossary
         */
        public static function instance() {
		_doing_it_wrong( 
			'FP\Multilanguage\Glossary::instance()', 
			'Glossary::instance() is deprecated. Use dependency injection via container instead.', 
			'1.0.0' 
		);
		
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Register glossary filters so providers can enforce terminology.
         *
         * @since 0.2.0
         *
         * @return void
         */
        protected function register_filters() {
                add_filter( '\FPML_glossary_pre_translate', array( $this, 'filter_pre_translate' ), 10, 4 );
                add_filter( '\FPML_glossary_post_translate', array( $this, 'filter_post_translate' ), 10, 4 );
        }

        /**
         * Load glossary entries from storage.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function load_entries() {
                $stored = get_option( self::OPTION_KEY, array() );

                if ( ! is_array( $stored ) ) {
                        $stored = array();
                }

                return $stored;
        }

        /**
         * Persist glossary entries.
         *
         * @since 0.2.0
         *
         * @param array $entries Entries to store.
         *
         * @return void
         */
        protected function save_entries( $entries ) {
                update_option( self::OPTION_KEY, $entries, false );
                $this->entries = $entries;
        }

        /**
         * Retrieve glossary entries.
         *
         * @since 0.2.0
         *
         * @return array
         */
        public function get_entries() {
                return $this->entries;
        }

        /**
         * Replace glossary entries with the provided list.
         *
         * @since 0.2.0
         *
         * @param array $entries Entries to persist.
         *
         * @return void
         */
        public function replace_entries( $entries ) {
                $sanitized = array();

                foreach ( $entries as $entry ) {
                        if ( empty( $entry['source'] ) || empty( $entry['target'] ) ) {
                                continue;
                        }

                        $key = md5( wp_json_encode( array( $entry['source'], $entry['context'] ?? '' ) ) );

                        $sanitized[ $key ] = array(
                                'source'     => sanitize_text_field( $entry['source'] ),
                                'target'     => sanitize_text_field( $entry['target'] ),
                                'context'    => isset( $entry['context'] ) ? sanitize_text_field( $entry['context'] ) : '',
                                'updated_at' => current_time( 'timestamp' ),
                        );
                }

                $this->save_entries( $sanitized );
        }

        /**
         * Add or update a single entry.
         *
         * @since 0.2.0
         *
         * @param string $source  Italian term.
         * @param string $target  English translation.
         * @param string $context Optional context label.
         *
         * @return void
         */
        public function upsert_entry( $source, $target, $context = '' ) {
                $source  = sanitize_text_field( $source );
                $target  = sanitize_text_field( $target );
                $context = sanitize_text_field( $context );

                if ( '' === $source || '' === $target ) {
                        return;
                }

                $key                       = md5( wp_json_encode( array( $source, $context ) ) );
                $this->entries[ $key ]     = array(
                        'source'     => $source,
                        'target'     => $target,
                        'context'    => $context,
                        'updated_at' => current_time( 'timestamp' ),
                );
                $this->save_entries( $this->entries );
        }

        /**
         * Delete glossary entries by keys.
         *
         * @since 0.2.0
         *
         * @param array $keys Keys to remove.
         *
         * @return void
         */
        public function delete_entries( $keys ) {
                if ( empty( $keys ) ) {
                        return;
                }

                foreach ( $keys as $key ) {
                        unset( $this->entries[ $key ] );
                }

                $this->save_entries( $this->entries );
        }

        /**
         * Export glossary to JSON.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function export_json() {
                return wp_json_encode( array_values( $this->entries ) );
        }

        /**
         * Export glossary to CSV.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function export_csv() {
                $rows = array();
                $rows[] = array( 'source', 'target', 'context' );

                foreach ( $this->entries as $entry ) {
                        $rows[] = array( $entry['source'], $entry['target'], $entry['context'] );
                }

                $buffer = fopen( 'php://temp', 'w+' );

                foreach ( $rows as $row ) {
                        fputcsv( $buffer, $row );
                }

                rewind( $buffer );
                $csv = stream_get_contents( $buffer );
                fclose( $buffer );

                return $csv;
        }

        /**
         * Import glossary from JSON payload.
         *
         * @since 0.2.0
         *
         * @param string $json JSON payload.
         *
         * @return int Number of imported entries.
         */
        public function import_json( $json ) {
                $decoded = json_decode( $json, true );

                if ( ! is_array( $decoded ) ) {
                        return 0;
                }

                $imported = 0;

                foreach ( $decoded as $entry ) {
                        if ( empty( $entry['source'] ) || empty( $entry['target'] ) ) {
                                continue;
                        }

                        $this->upsert_entry( $entry['source'], $entry['target'], isset( $entry['context'] ) ? $entry['context'] : '' );
                        $imported++;
                }

                return $imported;
        }

        /**
         * Import glossary from CSV payload.
         *
         * @since 0.2.0
         *
         * @param string $csv CSV payload.
         *
         * @return int Number of imported entries.
         */
        public function import_csv( $csv ) {
                $lines = explode( "\n", $csv );

                if ( empty( $lines ) ) {
                        return 0;
                }

                $imported = 0;
                $header   = true;

                foreach ( $lines as $line ) {
                        $line = trim( $line );

                        if ( '' === $line ) {
                                continue;
                        }

                        $columns = str_getcsv( $line );

                        if ( $header ) {
                                $header = false;
                                continue;
                        }

                        $source  = isset( $columns[0] ) ? $columns[0] : '';
                        $target  = isset( $columns[1] ) ? $columns[1] : '';
                        $context = isset( $columns[2] ) ? $columns[2] : '';

                        if ( '' === $source || '' === $target ) {
                                continue;
                        }

                        $this->upsert_entry( $source, $target, $context );
                        $imported++;
                }

                return $imported;
        }

        /**
         * Apply glossary replacements before sending text to providers.
         *
         * @since 0.2.0
         *
         * @param string              $text    Text to process.
         * @param string              $source  Source locale code.
         * @param string              $target  Target locale code.
         * @param string              $context Context domain.
         *
         * @return string
         */
        public function filter_pre_translate( $text, $source, $target, $context ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( ! is_string( $text ) || '' === $text ) {
                        return $text;
                }

                $container = $this->getContainer();
                $settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
                $case_sensitive  = $settings ? $settings->get( 'glossary_case_sensitive', false ) : false;
                $auto_detect_brands = $settings ? $settings->get( 'glossary_auto_detect_brands', true ) : true;

                // Applica voci del glossario manuale (se presenti)
                if ( ! empty( $this->entries ) ) {
                        $ordered_entries = $this->get_entries_sorted();
                        foreach ( $ordered_entries as $entry ) {
                                $text = $this->replace_glossary_term( $text, $entry, $case_sensitive );
                        }
                }

                // Rilevamento automatico di nomi di brand e termini non traducibili
                if ( $auto_detect_brands && 'it' === $source && 'en' === $target ) {
                        $text = $this->protect_brand_names_automatically( $text );
                }

                return $text;
        }

        /**
         * Restore placeholders after translation.
         *
         * @since 0.2.0
         *
         * @param string              $text    Text processed by provider.
         * @param string              $source  Source locale code.
         * @param string              $target  Target locale code.
         * @param string              $context Context domain.
         *
         * @return string
         */
        public function filter_post_translate( $text, $source, $target, $context ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                if ( ! is_string( $text ) || '' === $text ) {
                        return $text;
                }

                return preg_replace_callback(
                        '/\[\[FPML:([A-Za-z0-9+\/=\-_:]+)\]\]/',
                        static function( $matches ) {
                                $decoded = base64_decode( $matches[1], true );

                                if ( false === $decoded ) {
                                        return $matches[0];
                                }

                                return $decoded;
                        },
                        $text
                );
        }

        /**
         * Retrieve entries sorted by source length (DESC) to match longer phrases first.
         *
         * @since 0.2.0
         *
         * @return array
         */
        protected function get_entries_sorted() {
                $entries = $this->entries;

                uasort(
                        $entries,
                        static function( $a, $b ) {
                                return mb_strlen( $b['source'], 'UTF-8' ) <=> mb_strlen( $a['source'], 'UTF-8' );
                        }
                );

                return $entries;
        }

        /**
         * Replace occurrences of a glossary term within the text.
         *
         * @since 0.2.0
         *
         * @param string $text           Original text.
         * @param array  $entry          Glossary entry.
         * @param bool   $case_sensitive Whether to match case-sensitive.
         *
         * @return string
         */
        protected function replace_glossary_term( $text, $entry, $case_sensitive ) {
                $source = $entry['source'];
                $target = $entry['target'];

                if ( '' === $source || '' === $target ) {
                        return $text;
                }

                $pattern = '/' . preg_quote( $source, '/' ) . '/u' . ( $case_sensitive ? '' : 'i' );

                return preg_replace_callback(
                        $pattern,
                        static function( $matches ) use ( $target ) {
                                $placeholder = '[[FPML:' . base64_encode( $target ) . ']]';
                                // Preserve spacing around the match if present.
                                if ( preg_match( '/^(\s*)(.*?)(\s*)$/u', $matches[0], $parts ) ) {
                                        return $parts[1] . $placeholder . $parts[3];
                                }

                                return $placeholder;
                        },
                        $text
                );
        }

        /**
         * Rileva e protegge automaticamente nomi di brand e altri termini non traducibili.
         *
         * @since 0.10.0
         *
         * @param string $text Testo da analizzare.
         * @return string Testo con i termini protetti.
         */
        protected function protect_brand_names_automatically( $text ) {
                // Salta se il testo è vuoto o contiene solo HTML
                if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
                        return $text;
                }

                // Salta se il testo contiene già placeholder (già processato)
                if ( strpos( $text, '[[FPML:' ) !== false ) {
                        // Estrai placeholder esistenti per evitarli
                        $text = $this->protect_brand_names_with_placeholders( $text );
                        return $text;
                }

                // Lista estesa di parole comuni italiane da escludere (articoli, preposizioni, congiunzioni, verbi comuni, ecc.)
                $common_words = array(
                        // Articoli
                        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
                        // Preposizioni semplici
                        'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
                        // Preposizioni articolate
                        'del', 'della', 'dei', 'degli', 'delle', 'dall', 'dalla', 'dallo', 'dalle', 'dai', 'dagli', 'dal',
                        'nel', 'nella', 'nei', 'negli', 'nelle', 'sul', 'sulla', 'sui', 'sugli', 'sulle',
                        // Congiunzioni
                        'e', 'o', 'ma', 'che', 'se', 'come', 'quando', 'dove', 'perché',
                        // Verbi comuni
                        'essere', 'avere', 'fare', 'dire', 'andare', 'venire', 'vedere', 'sapere', 'volere', 'potere', 'dovere',
                        // Altri termini comuni
                        'questo', 'questa', 'quello', 'quella', 'quelli', 'quelle', 'tutto', 'tutta', 'tutti', 'tutte',
                        'molto', 'molta', 'molti', 'molte', 'più', 'meno', 'troppo', 'troppa', 'troppi', 'troppe'
                );

                // Lista di nomi comuni da escludere (città, paesi, nomi comuni che non sono brand)
                $common_names = array(
                        'italia', 'roma', 'milano', 'napoli', 'torino', 'firenze', 'bologna', 'venezia', 'genova',
                        'europa', 'africa', 'asia', 'america', 'oceania', 'europa', 'italiano', 'italiana', 'italiani', 'italiane',
                        'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica',
                        'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'
                );

                // Estrai HTML tags e placeholder per processare solo il testo
                $parts = preg_split( '/(<[^>]+>|\[\[FPML:[^\]]+\]\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
                $result = '';

                foreach ( $parts as $part ) {
                        // Ripristina HTML tags e placeholder esistenti senza modificarli
                        if ( preg_match( '/^(<[^>]+>|\[\[FPML:[^\]]+\]\])$/', $part ) ) {
                                $result .= $part;
                                continue;
                        }

                        // Processa solo il testo puro
                        $processed = $this->detect_and_protect_terms( $part, $common_words, $common_names );
                        $result .= $processed;
                }

                return $result;
        }

        /**
         * Rileva e protegge termini in un testo già parzialmente processato (con placeholder esistenti).
         *
         * @since 0.10.0
         *
         * @param string $text Testo da analizzare.
         * @return string Testo con i termini protetti.
         */
        protected function protect_brand_names_with_placeholders( $text ) {
                // Dividi il testo preservando placeholder esistenti
                $parts = preg_split( '/(\[\[FPML:[^\]]+\]\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
                $common_words = array(
                        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
                        'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
                        'del', 'della', 'dei', 'degli', 'delle', 'dall', 'dalla', 'dallo', 'dalle', 'dai', 'dagli', 'dal',
                        'nel', 'nella', 'nei', 'negli', 'nelle', 'sul', 'sulla', 'sui', 'sugli', 'sulle',
                        'e', 'o', 'ma', 'che', 'se', 'come', 'quando', 'dove', 'perché',
                        'essere', 'avere', 'fare', 'dire', 'andare', 'venire', 'vedere', 'sapere', 'volere', 'potere', 'dovere',
                        'questo', 'questa', 'quello', 'quella', 'quelli', 'quelle', 'tutto', 'tutta', 'tutti', 'tutte',
                        'molto', 'molta', 'molti', 'molte', 'più', 'meno', 'troppo', 'troppa', 'troppi', 'troppe'
                );
                $result = '';

                foreach ( $parts as $part ) {
                        if ( preg_match( '/^\[\[FPML:[^\]]+\]\]$/', $part ) ) {
                                $result .= $part;
                        } else {
                                $result .= $this->detect_and_protect_terms( $part, $common_words, $common_names );
                        }
                }

                return $result;
        }

        /**
         * Rileva e protegge termini in un testo puro (senza HTML o placeholder).
         * Usa analisi contestuale intelligente per identificare nomi di brand.
         *
         * @since 0.10.0
         *
         * @param string $text         Testo puro da analizzare.
         * @param array  $common_words Parole comuni da escludere.
         * @param array  $common_names Nomi comuni da escludere (città, paesi, ecc.).
         * @return string Testo con i termini protetti.
         */
        protected function detect_and_protect_terms( $text, $common_words, $common_names = array() ) {
                // APPROCCIO SICURO: proteggere solo pattern chiari e affidabili
                // 1. Acronimi (parole completamente maiuscole) - pattern molto chiaro
                // 2. Combinazioni di parole capitalizzate (es. "FP Multilanguage") - pattern chiaro
                // NON proteggiamo singole parole capitalizzate - troppo rischioso
                
                // Rileva acronimi (parole completamente maiuscole di almeno 2 caratteri)
                $text = preg_replace_callback(
                        '/\b([A-Z]{2,})\b/u',
                        static function( $matches ) {
                                $word = $matches[1];
                                // Escludi acronimi molto lunghi che probabilmente non sono brand
                                if ( mb_strlen( $word, 'UTF-8' ) > 10 ) {
                                        return $matches[0];
                                }
                                // Proteggi acronimi (mantieni originali durante traduzione)
                                $placeholder = '[[FPML:' . base64_encode( $word ) . ']]';
                                return $placeholder;
                        },
                        $text
                );

                // Rileva combinazioni di parole capitalizzate (es. "Nome Brand", "Multi Language")
                $text = preg_replace_callback(
                        '/\b([A-ZÀ-ÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ][a-zà-áâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]+(?:\s+[A-ZÀ-ÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ][a-zà-áâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]+){1,2})\b/u',
                        static function( $matches ) use ( $common_words ) {
                                $phrase = $matches[1];
                                
                                // Escludi se contiene parole comuni
                                $words = preg_split( '/\s+/u', $phrase );
                                foreach ( $words as $word ) {
                                        if ( in_array( mb_strtolower( $word, 'UTF-8' ), $common_words, true ) ) {
                                                return $matches[0];
                                        }
                                }
                                
                                // Escludi frasi molto lunghe
                                if ( mb_strlen( $phrase, 'UTF-8' ) > 50 ) {
                                        return $matches[0];
                                }
                                
                                // Proteggi la frase (mantiene l'originale durante la traduzione)
                                $placeholder = '[[FPML:' . base64_encode( $phrase ) . ']]';
                                return $placeholder;
                        },
                        $text
                );

                // NON proteggiamo singole parole capitalizzate - troppo rischioso
                // L'utente può aggiungere manualmente voci al glossario per brand names specifici
                // Questo approccio è molto più sicuro e affidabile
                // Inoltre, abbiamo migliorato il prompt di OpenAI per preservare brand names automaticamente

                return $text;
        }

        /**
         * Analizza la frequenza delle parole capitalizzate nel testo.
         * Le parole che appaiono più volte hanno più probabilità di essere brand.
         *
         * @since 0.10.0
         *
         * @param string $text Testo da analizzare.
         * @return array Array associativo con parole capitalizzate e loro frequenza.
         */
        protected function analyze_word_frequency( $text ) {
                $frequency = array();
                
                // Trova tutte le parole capitalizzate nel testo
                preg_match_all( '/\b([A-ZÀ-ÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞŸ][a-zà-áâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]{2,})\b/u', $text, $matches );
                
                if ( ! empty( $matches[1] ) ) {
                        foreach ( $matches[1] as $word ) {
                                $word_lower = mb_strtolower( $word, 'UTF-8' );
                                if ( ! isset( $frequency[ $word_lower ] ) ) {
                                        $frequency[ $word_lower ] = array(
                                                'original'   => $word,
                                                'count'      => 0,
                                                'positions'  => array(),
                                        );
                                }
                                $frequency[ $word_lower ]['count']++;
                        }
                }
                
                return $frequency;
        }

        /**
         * Analizza il contesto sintattico di una parola per determinare se è un brand.
         * Cerca pattern semantici che indicano nomi di brand (es. dopo "brand", "marchio", ecc.).
         *
         * @since 0.10.0
         *
         * @param string $text            Testo completo.
         * @param string $word            Parola da analizzare.
         * @param int    $position        Posizione della parola nel testo.
         * @param array  $brand_indicators Parole che indicano brand names.
         * @return bool True se il contesto suggerisce che è un brand.
         */
        protected function has_brand_context( $text, $word, $position, $brand_indicators ) {
                // Estrai contesto locale (50 caratteri prima e dopo)
                $context_start = max( 0, $position - 50 );
                $context_end = min( mb_strlen( $text, 'UTF-8' ), $position + mb_strlen( $word, 'UTF-8' ) + 50 );
                $context = mb_substr( $text, $context_start, $context_end - $context_start, 'UTF-8' );
                $context_lower = mb_strtolower( $context, 'UTF-8' );
                
                // Cerca indicatori di brand nel contesto
                foreach ( $brand_indicators as $indicator ) {
                        if ( mb_strpos( $context_lower, $indicator, 0, 'UTF-8' ) !== false ) {
                                // Verifica se l'indicatore è vicino alla parola
                                $indicator_pos = mb_strpos( $context_lower, $indicator, 0, 'UTF-8' );
                                $word_pos_in_context = $position - $context_start;
                                $distance = abs( $indicator_pos - $word_pos_in_context );
                                
                                // Se l'indicatore è entro 20 caratteri dalla parola, è probabile un brand
                                if ( $distance < 20 ) {
                                        return true;
                                }
                        }
                }
                
                // Cerca pattern comuni: "di [Word]", "da [Word]", "con [Word]", "[Word] è", "[Word] offre"
                $pattern_before = array( 'di ', 'da ', 'con ', 'per ', 'su ', 'il ', 'la ', 'del ', 'della ', 'dai ', 'dalle ' );
                $pattern_after = array( ' è', ' offre', ' permette', ' consente', ' fornisce', ' gestisce' );
                
                foreach ( $pattern_before as $pattern ) {
                        if ( mb_strpos( $context_lower, $pattern . mb_strtolower( $word, 'UTF-8' ), 0, 'UTF-8' ) !== false ) {
                                return true;
                        }
                }
                
                foreach ( $pattern_after as $pattern ) {
                        if ( mb_strpos( $context_lower, mb_strtolower( $word, 'UTF-8' ) . $pattern, 0, 'UTF-8' ) !== false ) {
                                return true;
                        }
                }
                
                return false;
        }
}

