<?php
namespace FPMultilanguage\Blocks;

use FPMultilanguage\Widgets\LanguageSwitcher;

class LanguageSwitcherBlock {

        private LanguageSwitcher $languageSwitcher;

        public function __construct( LanguageSwitcher $languageSwitcher ) {
                $this->languageSwitcher = $languageSwitcher;
        }

        public function register(): void {
                add_action( 'init', array( $this, 'register_block' ) );
        }

        public function register_block(): void {
                if ( ! function_exists( 'register_block_type' ) ) {
                        return;
                }

                wp_register_script(
                        'fp-multilanguage-language-switcher-block',
                        FP_MULTILANGUAGE_URL . 'assets/js/language-switcher-block.js',
                        array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
                        FP_MULTILANGUAGE_VERSION,
                        true
                );

                register_block_type(
                        'fp-multilanguage/language-switcher',
                        array(
                                'editor_script' => 'fp-multilanguage-language-switcher-block',
                                'render_callback' => array( $this, 'render_block' ),
                                'attributes'      => array(
                                        'layout' => array(
                                                'type'    => 'string',
                                                'default' => 'list',
                                        ),
                                ),
                                'supports'       => array(
                                        'html' => false,
                                ),
                        )
                );

                if ( function_exists( 'wp_set_script_translations' ) ) {
                        wp_set_script_translations(
                                'fp-multilanguage-language-switcher-block',
                                'fp-multilanguage',
                                FP_MULTILANGUAGE_PATH . 'languages'
                        );
                }
        }

        /**
         * @param array<string, mixed> $attributes
         */
        public function render_block( array $attributes = array(), string $content = '' ): string {
                unset( $content );

                $layout = isset( $attributes['layout'] ) ? (string) $attributes['layout'] : 'list';
                if ( ! in_array( $layout, array( 'list', 'inline' ), true ) ) {
                        $layout = 'list';
                }

                return $this->languageSwitcher->render_shortcode(
                        array(
                                'layout' => $layout,
                        )
                );
        }
}
