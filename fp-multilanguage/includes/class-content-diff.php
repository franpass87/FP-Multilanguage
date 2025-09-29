<?php
/**
 * Content diff helper to perform incremental translation while preserving markup.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Provide utilities to compute textual diffs that respect HTML/shortcodes boundaries.
 *
 * @since 0.2.0
 */
class FPML_Content_Diff {
        const SHORTCODE_PLACEHOLDER_PREFIX = '__FPML_SC_PLACEHOLDER_';
        const SHORTCODE_PLACEHOLDER_REGEX  = '/__FPML_SC_PLACEHOLDER_[A-Z0-9]{12}__/';
        /**
         * Singleton instance.
         *
         * @var FPML_Content_Diff|null
         */
        protected static $instance = null;

        /**
         * Retrieve singleton instance.
         *
         * @since 0.2.0
         *
         * @return FPML_Content_Diff
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Tokenize content into translatable text and structural tokens.
         *
         * @since 0.2.0
         *
         * @param string $content Raw HTML/content.
         *
         * @return array Array of tokens.
         */
        public function tokenize( $content ) {
                $content = is_string( $content ) ? $content : '';

                if ( '' === $content ) {
                        return array();
                }

                $shortcode_regex = function_exists( 'get_shortcode_regex' ) ? get_shortcode_regex() : '';
                $pattern         = $shortcode_regex ? '/(' . $shortcode_regex . '|<[^>]+>)/' : '/(<[^>]+>)/';

                $parts = preg_split( $pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

                $tokens      = array();
                $text_index  = 0;
                $shortcode_pattern = $shortcode_regex ? '/^' . $shortcode_regex . '$/' : '/^$/';

                foreach ( $parts as $part ) {
                        if ( '' === $part ) {
                                continue;
                        }

                        if ( '<' === $part[0] ) {
                                $tokens[] = array(
                                        'type'  => 'tag',
                                        'value' => $part,
                                );
                                continue;
                        }

                        if ( preg_match( self::SHORTCODE_PLACEHOLDER_REGEX, $part ) ) {
                                $tokens[] = array(
                                        'type'  => 'placeholder',
                                        'value' => $part,
                                );
                                continue;
                        }

                        if ( $shortcode_regex && preg_match( $shortcode_pattern, $part ) ) {
                                $tokens[] = array(
                                        'type'  => 'shortcode',
                                        'value' => $part,
                                );
                                continue;
                        }

                        $tokens[] = $this->prepare_text_token( $part, $text_index );
                        $text_index++;
                }

                return $tokens;
        }

        /**
         * Extract map of text segments from tokenized content.
         *
         * @since 0.2.0
         *
         * @param array $tokens Tokens previously produced by tokenize().
         *
         * @return array Map of index => segment data.
         */
        public function extract_text_map( $tokens ) {
                $map = array();

                foreach ( $tokens as $token ) {
                        if ( 'text' !== $token['type'] ) {
                                continue;
                        }

                        $index = (int) $token['index'];
                        $map[ $index ] = $token;
                }

                return $map;
        }

        /**
         * Normalize shortcode slugs to compare against the exclusion list.
         *
         * @since 0.2.1
         *
         * @param array $shortcodes Raw shortcode identifiers.
         *
         * @return array
         */
        protected function normalize_excluded_shortcodes( $shortcodes ) {
                if ( empty( $shortcodes ) ) {
                        return array();
                }

                $normalized = array();

                foreach ( (array) $shortcodes as $shortcode ) {
                        if ( ! is_string( $shortcode ) ) {
                                continue;
                        }

                        $slug = strtolower( trim( preg_replace( '/[^a-z0-9_-]/i', '', $shortcode ) ) );

                        if ( '' !== $slug ) {
                                $normalized[] = $slug;
                        }
                }

                return array_values( array_unique( $normalized ) );
        }

        /**
         * Replace excluded shortcode instances with placeholders.
         *
         * @since 0.2.1
         *
         * @param string $content   Content to inspect.
         * @param array  $shortcodes Shortcode slugs to exclude.
         *
         * @return array Tuple containing the masked content and the placeholder map.
         */
        protected function mask_excluded_shortcodes( $content, $shortcodes ) {
                $content = is_string( $content ) ? $content : '';

                if ( '' === $content || empty( $shortcodes ) ) {
                        return array( $content, array() );
                }

                $map     = array();
                $pattern = '/\[([a-zA-Z0-9_\-]+)([^\]]*)\](?:.*?\[\/\1\])?/s';

                $masked = preg_replace_callback(
                        $pattern,
                        function( $matches ) use ( $shortcodes, &$map ) {
                                $tag = strtolower( $matches[1] );

                                if ( ! in_array( $tag, $shortcodes, true ) ) {
                                        return $matches[0];
                                }

                                $index       = count( $map );
                                $hash        = strtoupper( substr( md5( $matches[0] . '|' . $index ), 0, 12 ) );
                                $placeholder = self::SHORTCODE_PLACEHOLDER_PREFIX . $hash . '__';

                                while ( isset( $map[ $placeholder ] ) ) {
                                        $index++;
                                        $hash        = strtoupper( substr( md5( $matches[0] . '|' . $index ), 0, 12 ) );
                                        $placeholder = self::SHORTCODE_PLACEHOLDER_PREFIX . $hash . '__';
                                }

                                $map[ $placeholder ] = $matches[0];

                                return $placeholder;
                        },
                        $content
                );

                if ( null === $masked ) {
                        return array( $content, $map );
                }

                return array( $masked, $map );
        }

        /**
         * Prepare text for provider requests by masking excluded shortcodes.
         *
         * @since 0.2.1
         *
         * @param string $content   Text chunk to process.
         * @param array  $shortcodes Shortcode slugs to exclude from translation.
         *
         * @return array Tuple containing the masked content and the placeholder map.
         */
        public function prepare_text_for_provider( $content, $shortcodes ) {
                $normalized = $this->normalize_excluded_shortcodes( $shortcodes );

                if ( empty( $normalized ) ) {
                        $content = is_string( $content ) ? $content : '';

                        return array( $content, array() );
                }

                return $this->mask_excluded_shortcodes( $content, $normalized );
        }

        /**
         * Restore excluded shortcode placeholders to their original markup.
         *
         * @since 0.2.1
         *
         * @param string $content Content containing placeholders.
         * @param array  $map     Placeholder map generated during masking.
         *
         * @return string
         */
        public function restore_placeholders( $content, $map ) {
                $content = is_string( $content ) ? $content : '';

                if ( '' === $content || empty( $map ) ) {
                        return $content;
                }

                $replacements = array();

                foreach ( (array) $map as $placeholder => $original ) {
                        if ( ! is_string( $placeholder ) || '' === $placeholder ) {
                                continue;
                        }

                        $replacements[ $placeholder ] = is_string( $original ) ? $original : '';
                }

                if ( empty( $replacements ) ) {
                        return $content;
                }

                return strtr( $content, $replacements );
        }

        /**
         * Determine which segments changed between source and target content.
         *
         * @since 0.2.0
         *
         * @param string $source  Source text (Italiano).
         * @param string $target  Existing translation (English).
         * @param array  $context Additional diff configuration.
         *
         * @return array Diff structure containing segments to translate and helpers for merge.
         */
        public function calculate_diff( $source, $target, $context = array() ) {
                $source = is_string( $source ) ? $source : (string) $source;
                $target = is_string( $target ) ? $target : (string) $target;

                $excluded_shortcodes = array();
                if ( isset( $context['excluded_shortcodes'] ) ) {
                        $excluded_shortcodes = $this->normalize_excluded_shortcodes( $context['excluded_shortcodes'] );
                }

                $placeholder_map = array();

                if ( ! empty( $excluded_shortcodes ) ) {
                        list( $source, $source_map ) = $this->mask_excluded_shortcodes( $source, $excluded_shortcodes );
                        list( $target, $target_map ) = $this->mask_excluded_shortcodes( $target, $excluded_shortcodes );
                        $placeholder_map          = $source_map + $target_map;
                }

                $source_tokens = $this->tokenize( $source );
                $target_tokens = $this->tokenize( $target );

                $source_map = $this->extract_text_map( $source_tokens );
                $target_map = $this->extract_text_map( $target_tokens );

                $to_translate = array();

                foreach ( $source_map as $index => $token ) {
                        $source_normalized = $this->normalize_for_diff( $token['text'] );
                        $target_token      = isset( $target_map[ $index ] ) ? $target_map[ $index ] : null;
                        $target_normalized = $target_token ? $this->normalize_for_diff( $target_token['text'] ) : '';

                        if ( '' === $source_normalized ) {
                                continue;
                        }

                        if ( $source_normalized !== $target_normalized ) {
                                $to_translate[ $index ] = $token['text'];
                        }
                }

                return array(
                        'source_tokens'   => $source_tokens,
                        'target_tokens'   => $target_tokens,
                        'source_map'      => $source_map,
                        'target_map'      => $target_map,
                        'segments'        => $to_translate,
                        'placeholder_map' => $placeholder_map,
                );
        }

        /**
         * Merge translated segments back into the original structure.
         *
         * @since 0.2.0
         *
         * @param array $source_tokens    Tokens generated from Italian source.
         * @param array $target_map       Existing translation text map.
         * @param array $translations     Map of index => translated string.
         * @param array $placeholder_map  Placeholder map for excluded shortcodes.
         *
         * @return string Reconstructed content for the translated entity.
         */
        public function rebuild( $source_tokens, $target_map, $translations, $placeholder_map = array() ) {
                $output = array();

                foreach ( $source_tokens as $token ) {
                        if ( 'text' !== $token['type'] ) {
                                $output[] = $token['value'];
                                continue;
                        }

                        $index = (int) $token['index'];

                        if ( isset( $translations[ $index ] ) ) {
                                $value = $translations[ $index ];
                        } elseif ( isset( $target_map[ $index ] ) ) {
                                $value = $target_map[ $index ]['text'];
                        } else {
                                $value = $token['text'];
                        }

                        $value     = is_string( $value ) ? $value : '';
                        $value     = $this->restore_whitespace( $value, $token );
                        $output[] = $value;
                }

                $result = implode( '', $output );

                return $this->restore_placeholders( $result, $placeholder_map );
        }

        /**
         * Prepare a token entry for text segments.
         *
         * @since 0.2.0
         *
         * @param string $text       Raw text segment.
         * @param int    $text_index Running index.
         *
         * @return array Token data structure.
         */
        protected function prepare_text_token( $text, $text_index ) {
                $text = (string) $text;

                preg_match( '/^(\s*)(.*?)(\s*)$/s', $text, $matches );

                $leading = isset( $matches[1] ) ? $matches[1] : '';
                $core    = isset( $matches[2] ) ? $matches[2] : '';
                $trailing = isset( $matches[3] ) ? $matches[3] : '';

                return array(
                        'type'   => 'text',
                        'value'  => $text,
                        'text'   => $core,
                        'index'  => (int) $text_index,
                        'prefix' => $leading,
                        'suffix' => $trailing,
                );
        }

        /**
         * Restore whitespace around a translated string.
         *
         * @since 0.2.0
         *
         * @param string $text   Translated text.
         * @param array  $token  Original token metadata.
         *
         * @return string
         */
        protected function restore_whitespace( $text, $token ) {
                $prefix = isset( $token['prefix'] ) ? $token['prefix'] : '';
                $suffix = isset( $token['suffix'] ) ? $token['suffix'] : '';

                return $prefix . $text . $suffix;
        }

        /**
         * Normalize strings for comparison purposes.
         *
         * @since 0.2.0
         *
         * @param string $text Text to normalize.
         *
         * @return string
         */
        protected function normalize_for_diff( $text ) {
                $text = is_string( $text ) ? $text : '';
                $text = wp_strip_all_tags( $text );
                $text = preg_replace( '/\s+/u', ' ', $text );

                return trim( $text );
        }
}
