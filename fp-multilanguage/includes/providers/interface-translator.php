<?php
/**
 * Translator interface and shared helpers.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Translator interface.
 *
 * @since 0.2.0
 */
interface FPML_TranslatorInterface {
        /**
         * Unique provider slug.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function get_slug();

        /**
         * Translate text from source to target language.
         *
         * @since 0.2.0
         *
         * @param string $text   Text to translate.
         * @param string $source Source language code.
         * @param string $target Target language code.
         * @param string $domain Context domain (marketing, general, seo...).
         *
         * @return string|WP_Error
         */
        public function translate( $text, $source = 'it', $target = 'en', $domain = 'general' );

        /**
         * Determine if the provider is ready to be used.
         *
         * @since 0.2.0
         *
         * @return bool
         */
        public function is_configured();

        /**
         * Estimate translation cost for a given text.
         *
         * @since 0.2.0
         *
         * @param string $text Text to evaluate.
         *
         * @return float
         */
        public function estimate_cost( $text );
}

/**
 * Base provider helper.
 *
 * @since 0.2.0
 */
abstract class FPML_Base_Provider implements FPML_TranslatorInterface {
        /**
         * Cached settings instance.
         *
         * @var FPML_Settings
         */
        protected $settings;

        /**
         * Constructor.
         */
        public function __construct() {
                $this->settings = FPML_Settings::instance();
        }

        /**
         * Retrieve an option from settings.
         *
         * @since 0.2.0
         *
         * @param string $key     Option key.
         * @param mixed  $default Default value.
         *
         * @return mixed
         */
        protected function get_option( $key, $default = '' ) {
                return $this->settings ? $this->settings->get( $key, $default ) : $default;
        }

        /**
         * Apply glossary replacements before translation.
         *
         * @since 0.2.0
         *
         * @param string $text   Text to process.
         * @param string $source Source language code.
         * @param string $target Target language code.
         * @param string $domain Context domain.
         *
         * @return string
         */
        protected function apply_glossary_pre( $text, $source, $target, $domain ) {
                /**
                 * Filter glossary substitutions before translation.
                 *
                 * @since 0.2.0
                 *
                 * @param string              $text   Text to process.
                 * @param string              $source Source language.
                 * @param string              $target Target language.
                 * @param string              $domain Context domain.
                 * @param FPML_Base_Provider $provider Provider instance.
                 */
                return apply_filters( 'fpml_glossary_pre_translate', $text, $source, $target, $domain, $this );
        }

        /**
         * Apply glossary replacements after translation.
         *
         * @since 0.2.0
         *
         * @param string $text   Text to process.
         * @param string $source Source language code.
         * @param string $target Target language code.
         * @param string $domain Context domain.
         *
         * @return string
         */
        protected function apply_glossary_post( $text, $source, $target, $domain ) {
                /**
                 * Filter glossary substitutions after translation.
                 *
                 * @since 0.2.0
                 *
                 * @param string              $text   Text to process.
                 * @param string              $source Source language.
                 * @param string              $target Target language.
                 * @param string              $domain Context domain.
                 * @param FPML_Base_Provider $provider Provider instance.
                 */
                return apply_filters( 'fpml_glossary_post_translate', $text, $source, $target, $domain, $this );
        }

        /**
         * Chunk large texts while preserving HTML structure.
         *
         * @since 0.2.0
         *
         * @param string $text  Text to split.
         * @param int    $limit Character limit per chunk.
         *
         * @return array
         */
        protected function chunk_text( $text, $limit ) {
                $limit = max( 500, absint( $limit ) );

                if ( mb_strlen( $text, 'UTF-8' ) <= $limit ) {
                        return array( $text );
                }

                $parts  = function_exists( 'wp_html_split' ) ? wp_html_split( $text ) : array( $text );
                $chunks = array();
                $buffer = '';

                foreach ( $parts as $part ) {
                        $part_length = mb_strlen( $part, 'UTF-8' );

                        if ( $part_length > $limit ) {
                                $subparts = $this->split_long_text( $part, $limit );
                                foreach ( $subparts as $subpart ) {
                                        $chunks[] = $subpart;
                                }
                                $buffer = '';
                                continue;
                        }

                        $buffer_length = mb_strlen( $buffer, 'UTF-8' );
                        if ( $buffer_length + $part_length > $limit && '' !== $buffer ) {
                                $chunks[] = $buffer;
                                $buffer   = '';
                        }

                        $buffer .= $part;
                }

                if ( '' !== $buffer ) {
                        $chunks[] = $buffer;
                }

                if ( empty( $chunks ) ) {
                        $chunks = array( $text );
                }

                return $chunks;
        }

        /**
         * Split an oversized text chunk by whitespace to respect limits.
         *
         * @since 0.2.0
         *
         * @param string $text  Text to split.
         * @param int    $limit Character limit per chunk.
         *
         * @return array
         */
        protected function split_long_text( $text, $limit ) {
                $segments = array();
                $length   = mb_strlen( $text, 'UTF-8' );
                $offset   = 0;

                while ( $offset < $length ) {
                        $remaining = $length - $offset;
                        $take      = min( $limit, $remaining );
                        $slice     = mb_substr( $text, $offset, $take, 'UTF-8' );

                        if ( $take === $remaining ) {
                                $segments[] = $slice;
                                break;
                        }

                        $breakpoint = $this->find_breakpoint( $slice );
                        $segments[] = mb_substr( $slice, 0, $breakpoint, 'UTF-8' );
                        $offset    += $breakpoint;
                }

                return $segments;
        }

        /**
         * Find a safe breakpoint for a slice.
         *
         * @since 0.2.0
         *
         * @param string $slice Slice to inspect.
         *
         * @return int
         */
        protected function find_breakpoint( $slice ) {
                $length = mb_strlen( $slice, 'UTF-8' );

                $candidates = array( '\n\n', '\n', '. ', ' ', '\t' );
                foreach ( $candidates as $candidate ) {
                        $pos = mb_strrpos( $slice, $candidate, 0, 'UTF-8' );
                        if ( false !== $pos && $pos > 0 ) {
                                return $pos + mb_strlen( $candidate, 'UTF-8' );
                        }
                }

                return $length;
        }

        /**
         * Backoff helper with jitter.
         *
         * @since 0.2.0
         *
         * @param int $attempt Attempt number starting from 1.
         *
         * @return void
         */
        protected function backoff( $attempt ) {
                $attempt = max( 1, absint( $attempt ) );
                $delay   = min( pow( 2, $attempt - 1 ), 30 );
                $jitter  = function_exists( 'wp_rand' ) ? wp_rand( 0, 1000 ) / 1000 : mt_rand( 0, 1000 ) / 1000;
                $delay  += $jitter;

                if ( function_exists( 'wp_sleep' ) ) {
                        wp_sleep( $delay );
                } else {
                        usleep( (int) ( $delay * 1000000 ) );
                }
        }

        /**
         * Retrieve rate option key.
         *
         * @since 0.2.0
         *
         * @return string
         */
        abstract protected function get_rate_option_key();

        /**
         * Estimate cost based on configured rate per 1000 characters.
         *
         * @since 0.2.0
         *
         * @param string $text Text to evaluate.
         *
         * @return float
         */
        public function estimate_cost( $text ) {
                $rate_option = $this->get_option( $this->get_rate_option_key(), '' );
                if ( '' === $rate_option ) {
                        return 0.0;
                }

                $rate_option = str_replace( ',', '.', $rate_option );
                $rate        = (float) $rate_option;

                if ( $rate <= 0 ) {
                        return 0.0;
                }

                $characters = mb_strlen( $text, 'UTF-8' );
                $cost       = ( $characters / 1000 ) * $rate;

                /**
                 * Filter the estimated cost before returning.
                 *
                 * @since 0.2.0
                 *
                 * @param float               $cost       Estimated cost.
                 * @param int                 $characters Character count.
                 * @param float               $rate       Rate per 1000 characters.
                 * @param FPML_Base_Provider $provider   Provider instance.
                 */
                return (float) apply_filters( 'fpml_translate_estimated_cost', $cost, $characters, $rate, $this );
        }
}
