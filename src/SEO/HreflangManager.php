<?php
/**
 * SEO Hreflang Manager - Handles hreflang link generation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages hreflang link generation for multilingual content.
 *
 * @since 0.10.0
 */
class HreflangManager {
    /**
     * Language helper instance.
     *
     * @var \FPML_Language
     */
    protected $language;

    /**
     * Constructor.
     *
     * @param \FPML_Language $language Language helper instance.
     */
    public function __construct( $language ) {
        $this->language = $language;
    }

    /**
     * Build hreflang associations for the current object.
     *
     * @since 0.10.0
     *
     * @return array
     */
    public function get_hreflang_links() {
        $links = array();

        if ( is_front_page() || is_home() ) {
            // Get language manager to get enabled languages
            $language_manager = fpml_get_language_manager();
            if ( ! $language_manager ) {
                return $links;
            }
            $enabled_languages = $language_manager->get_enabled_languages();
            $all_languages = $language_manager->get_all_languages();

            // Map of language codes to BCP 47 locales
            $locale_map = array(
                'it' => 'it-IT',
                'en' => 'en-US',
                'de' => 'de-DE',
                'fr' => 'fr-FR',
                'es' => 'es-ES',
            );

            // Add Italian (source) link
            $italian = home_url( '/' );
            if ( $italian ) {
                $links[] = array(
                    'lang' => 'it-IT',
                    'url'  => $italian,
                );
            }

            // Add links for all enabled languages
            foreach ( $enabled_languages as $lang_code ) {
                if ( ! isset( $locale_map[ $lang_code ] ) || ! isset( $all_languages[ $lang_code ] ) ) {
                    continue;
                }

                $lang_info = $all_languages[ $lang_code ];
                if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
                    continue;
                }

                $lang_url = home_url( $lang_info['slug'] );
                if ( $lang_url ) {
                    $links[] = array(
                        'lang' => $locale_map[ $lang_code ],
                        'url'  => $lang_url,
                    );
                }
            }

            // Add x-default if filter allows
            if ( ! empty( $links ) && apply_filters( '\FPML_output_xdefault', true ) ) {
                // Use first enabled language or Italian as default
                $default_url = $italian;
                if ( ! empty( $enabled_languages ) && isset( $all_languages[ $enabled_languages[0] ] ) ) {
                    $first_lang_info = $all_languages[ $enabled_languages[0] ];
                    if ( is_array( $first_lang_info ) && ! empty( $first_lang_info['slug'] ) ) {
                        $default_url = home_url( $first_lang_info['slug'] );
                    }
                }
                $links[] = array(
                    'lang' => 'x-default',
                    'url'  => $default_url ? $default_url : $italian,
                );
            }

            return $links;
        }

        if ( is_singular() ) {
            $object = get_queried_object();

            if ( ! $object instanceof \WP_Post ) {
                return $links;
            }

            // Get language manager to get enabled languages
            $language_manager = fpml_get_language_manager();
            if ( ! $language_manager ) {
                return $links;
            }
            $enabled_languages = $language_manager->get_enabled_languages();
            $all_languages = $language_manager->get_all_languages();

            // Map of language codes to BCP 47 locales
            $locale_map = array(
                'it' => 'it-IT',
                'en' => 'en-US',
                'de' => 'de-DE',
                'fr' => 'fr-FR',
                'es' => 'es-ES',
            );

            // Determine source post
            $source_post = null;
            if ( get_post_meta( $object->ID, '_fpml_is_translation', true ) ) {
                // This is a translation, get the source
                $source_id = (int) get_post_meta( $object->ID, '_fpml_pair_source_id', true );
                if ( $source_id ) {
                    $source_post = get_post( $source_id );
                }
            } else {
                // This is the source
                $source_post = $object;
            }

            // Add Italian (source) link
            if ( $source_post instanceof \WP_Post ) {
                $italian_url = get_permalink( $source_post->ID );
                if ( $italian_url ) {
                    $links[] = array(
                        'lang' => 'it-IT',
                        'url'  => $italian_url,
                    );
                }
            }

            // Add links for all enabled languages
            foreach ( $enabled_languages as $lang_code ) {
                if ( ! isset( $locale_map[ $lang_code ] ) ) {
                    continue;
                }

                $translation_id = false;
                if ( get_post_meta( $object->ID, '_fpml_is_translation', true ) ) {
                    // Current post is a translation, get source first
                    $source_id = (int) get_post_meta( $object->ID, '_fpml_pair_source_id', true );
                    if ( $source_id ) {
                        // Get translation for this language from source
                        $translation_id = fpml_get_translation_id( $source_id, $lang_code );
                    }
                    // If current post is the translation for this language, use it
                    $current_lang = get_post_meta( $object->ID, '_fpml_target_language', true );
                    if ( $current_lang === $lang_code ) {
                        $translation_id = $object->ID;
                    }
                } else {
                    // Current post is source, get translation for this language
                    $translation_id = fpml_get_translation_id( $object->ID, $lang_code );
                }

                if ( $translation_id ) {
                    $translation_post = get_post( $translation_id );
                    if ( $translation_post instanceof \WP_Post ) {
                        $translation_url = get_permalink( $translation_id );
                        if ( $translation_url ) {
                            $links[] = array(
                                'lang' => $locale_map[ $lang_code ],
                                'url'  => $translation_url,
                            );
                        }
                    }
                }
            }

            return $links;
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();

            if ( ! $term instanceof \WP_Term ) {
                return $links;
            }

            // Get language manager
            $language_manager = fpml_get_language_manager();
            if ( ! $language_manager ) {
                return $links;
            }
            $enabled_languages = $language_manager->get_enabled_languages();
            $all_languages = $language_manager->get_all_languages();

            // Map of language codes to BCP 47 locales
            $locale_map = array(
                'it' => 'it-IT',
                'en' => 'en-US',
                'de' => 'de-DE',
                'fr' => 'fr-FR',
                'es' => 'es-ES',
            );

            // Determine source term
            $source_term = null;
            if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
                $source_id = (int) get_term_meta( $term->term_id, '_fpml_pair_source_id', true );
                $source_term = $source_id ? get_term( $source_id ) : null;
            } else {
                $source_term = $term;
            }

            // Add Italian (source) link
            if ( $source_term instanceof \WP_Term ) {
                $italian_link = get_term_link( $source_term );
                if ( ! is_wp_error( $italian_link ) ) {
                    $links[] = array(
                        'lang' => 'it-IT',
                        'url'  => $italian_link,
                    );
                }
            }

            // Add links for all enabled languages
            foreach ( $enabled_languages as $lang_code ) {
                if ( ! isset( $locale_map[ $lang_code ] ) ) {
                    continue;
                }

                $translation_term_id = false;
                if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
                    // Current term is a translation, get source first
                    $source_id = (int) get_term_meta( $term->term_id, '_fpml_pair_source_id', true );
                    if ( $source_id ) {
                        // Get translation for this language from source using new meta key
                        $meta_key = '_fpml_pair_id_' . $lang_code;
                        $translation_term_id = (int) get_term_meta( $source_id, $meta_key, true );
                    }
                    // If current term is the translation for this language, use it
                    $current_lang = get_term_meta( $term->term_id, '_fpml_target_language', true );
                    if ( $current_lang === $lang_code ) {
                        $translation_term_id = $term->term_id;
                    }
                } else {
                    // Current term is source, get translation for this language using new meta key
                    $meta_key = '_fpml_pair_id_' . $lang_code;
                    $translation_term_id = (int) get_term_meta( $term->term_id, $meta_key, true );
                    // Backward compatibility: check legacy _fpml_pair_id if lang is 'en'
                    if ( ! $translation_term_id && 'en' === $lang_code ) {
                        $translation_term_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );
                    }
                }

                if ( $translation_term_id ) {
                    $translation_term = get_term( $translation_term_id );
                    if ( $translation_term instanceof \WP_Term ) {
                        $translation_link = get_term_link( $translation_term );
                        if ( ! is_wp_error( $translation_link ) ) {
                            $links[] = array(
                                'lang' => $locale_map[ $lang_code ],
                                'url'  => $translation_link,
                            );
                        }
                    }
                }
            }
        }

        return $links;
    }
}
















