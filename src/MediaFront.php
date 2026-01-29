<?php
/**
 * Front-end media helpers.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Replace media references with their English counterparts on the frontend.
 *
 * @since 0.3.0
 */
class MediaFront {
        /**
         * Singleton instance.
         *
         * @var \FPML_Media_Front|null
         */
        protected static $instance = null;

        /**
         * Cached language helper.
         *
         * @var \FPML_Language|null
         */
        protected $language = null;

        /**
         * Attachment translation cache.
         *
         * @var array
         */
        protected $attachment_cache = array();

        /**
         * Retrieve singleton instance.
         *
         * @since 0.3.0
         *
         * @return \FPML_Media_Front
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        protected function __construct() {
                $this->language = class_exists( '\FPML_Language' ) ? ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() ) : null;

                if ( is_admin() ) {
                        return;
                }

                add_filter( 'the_content', array( $this, 'filter_content_media' ), 20 );
        }

        /**
         * Replace Italian attachment references with English IDs on the frontend.
         *
         * @since 0.3.0
         *
         * @param string $content Post content.
         *
         * @return string
         */
        public function filter_content_media( $content ) {
                if ( ! is_string( $content ) || '' === $content ) {
                        return $content;
                }

                if ( $this->language instanceof \FPML_Language && \FPML_Language::TARGET !== $this->language->get_current_language() ) {
                        return $content;
                }

                if ( false === strpos( $content, 'wp-image-' ) && false === strpos( $content, 'data-id="' ) && false === stripos( $content, '[gallery' ) && false === stripos( $content, 'vc_single_image' ) ) {
                        return $content;
                }

                $content = preg_replace_callback( '/wp-image-(\d+)/', array( $this, 'replace_class_reference' ), $content );
                $content = preg_replace_callback( '/data-id="(\d+)"/', array( $this, 'replace_data_attribute' ), $content );
                $content = preg_replace_callback( '/\[gallery([^\]]*?)ids="([^"]+)"([^\]]*?)\]/i', array( $this, 'replace_gallery_shortcode' ), $content );
                $content = preg_replace_callback( '/\[vc_single_image([^\]]*?)image="([0-9]+)"([^\]]*?)\]/i', array( $this, 'replace_vc_single_image' ), $content );

                return $content;
        }

        /**
         * Replace attachment class references in post content.
         *
         * @since 0.3.0
         *
         * @param array $matches Regex matches.
         *
         * @return string
         */
        protected function replace_class_reference( $matches ) {
                $original_id = isset( $matches[1] ) ? absint( $matches[1] ) : 0;

                if ( ! $original_id ) {
                        return $matches[0];
                }

                $translated_id = $this->get_translated_attachment_id( $original_id );

                if ( ! $translated_id ) {
                        return $matches[0];
                }

                return 'wp-image-' . $translated_id;
        }

        /**
         * Replace data-id attributes referencing attachments.
         *
         * @since 0.3.0
         *
         * @param array $matches Regex matches.
         *
         * @return string
         */
        protected function replace_data_attribute( $matches ) {
                $original_id = isset( $matches[1] ) ? absint( $matches[1] ) : 0;

                if ( ! $original_id ) {
                        return $matches[0];
                }

                $translated_id = $this->get_translated_attachment_id( $original_id );

                if ( ! $translated_id ) {
                        return $matches[0];
                }

                return 'data-id="' . $translated_id . '"';
        }

        /**
         * Replace gallery shortcode IDs with English attachments.
         *
         * @since 0.3.0
         *
         * @param array $matches Regex matches.
         *
         * @return string
         */
        protected function replace_gallery_shortcode( $matches ) {
                $before  = isset( $matches[1] ) ? $matches[1] : '';
                $id_list = isset( $matches[2] ) ? $matches[2] : '';
                $after   = isset( $matches[3] ) ? $matches[3] : '';

                $updated = preg_replace_callback(
                        '/\d+/',
                        function( $id_match ) {
                                $attachment_id = absint( $id_match[0] );

                                if ( ! $attachment_id ) {
                                        return $id_match[0];
                                }

                                $translated_id = $this->get_translated_attachment_id( $attachment_id );

                                if ( ! $translated_id ) {
                                        return $id_match[0];
                                }

                                return (string) $translated_id;
                        },
                        $id_list
                );

                if ( ! is_string( $updated ) || '' === $updated ) {
                        return $matches[0];
                }

                return '[gallery' . $before . 'ids="' . $updated . '"' . $after . ']';
        }

        /**
         * Replace VC single image shortcode IDs with English attachments.
         *
         * @since 0.3.0
         *
         * @param array $matches Regex matches.
         *
         * @return string
         */
        protected function replace_vc_single_image( $matches ) {
                $before = isset( $matches[1] ) ? $matches[1] : '';
                $id     = isset( $matches[2] ) ? absint( $matches[2] ) : 0;
                $after  = isset( $matches[3] ) ? $matches[3] : '';

                if ( ! $id ) {
                        return $matches[0];
                }

                $translated_id = $this->get_translated_attachment_id( $id );

                if ( ! $translated_id ) {
                        return $matches[0];
                }

                return '[vc_single_image' . $before . 'image="' . $translated_id . '"' . $after . ']';
        }

        /**
         * Resolve the English attachment ID for a given Italian attachment.
         *
         * @since 0.3.0
         *
         * @param int $attachment_id Attachment ID.
         *
         * @return int
         */
        protected function get_translated_attachment_id( $attachment_id ) {
                $attachment_id = absint( $attachment_id );

                if ( ! $attachment_id ) {
                        return 0;
                }

                if ( isset( $this->attachment_cache[ $attachment_id ] ) ) {
                        return (int) $this->attachment_cache[ $attachment_id ];
                }

                $result = $attachment_id;
                $post   = get_post( $attachment_id );

                if ( $post instanceof WP_Post && 'attachment' === $post->post_type ) {
                        if ( get_post_meta( $attachment_id, '_fpml_is_translation', true ) ) {
                                $this->attachment_cache[ $attachment_id ] = $result;

                                return $result;
                        }

                        $target_id = (int) get_post_meta( $attachment_id, '_fpml_pair_id', true );

                        if ( $target_id > 0 ) {
                                $target_post = get_post( $target_id );

                                if ( $target_post instanceof WP_Post && 'attachment' === $target_post->post_type ) {
                                        $result = $target_post->ID;
                                }
                        }
                }

                $this->attachment_cache[ $attachment_id ] = $result;

                return $result;
        }
}

