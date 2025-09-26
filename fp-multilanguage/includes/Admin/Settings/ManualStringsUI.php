<?php
namespace FPMultilanguage\Admin\Settings;

use FPMultilanguage\Services\Logger;

class ManualStringsUI {
    private Repository $repository;

    private Logger $logger;

    public function __construct( Repository $repository, Logger $logger ) {
        $this->repository = $repository;
        $this->logger     = $logger;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $strings   = $this->repository->get_manual_strings_catalog();
        $languages = $this->get_manual_string_languages( $strings );

        $updatedFlag = isset( $_GET['strings-updated'] ) ? sanitize_key( (string) $_GET['strings-updated'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $showSuccess = '1' === $updatedFlag;

        echo '<div class="wrap fp-multilanguage-strings">';
        echo '<h1>' . esc_html__( 'Stringhe dinamiche', 'fp-multilanguage' ) . '</h1>';

        if ( $showSuccess ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Traduzioni manuali aggiornate.', 'fp-multilanguage' ) . '</p></div>';
        }

        if ( empty( $strings ) ) {
            echo '<p>' . esc_html__( 'Non sono ancora state intercettate stringhe dinamiche. Aggiungi l\'attributo data-fp-translatable agli elementi front-end e visita le pagine per popolare l\'elenco.', 'fp-multilanguage' ) . '</p>';
            echo '</div>';

            return;
        }

        echo '<p>' . esc_html__( 'Rivedi e sovrascrivi le traduzioni manuali salvate dal front-end. Lascia vuoto un campo per ripristinare la traduzione automatica.', 'fp-multilanguage' ) . '</p>';

        echo '<div class="fp-multilanguage-strings-toolbar" style="margin:1em 0;">';
        echo '<label for="fp-multilanguage-strings-search" class="screen-reader-text">' . esc_html__( 'Cerca stringhe', 'fp-multilanguage' ) . '</label>';
        echo '<input type="search" id="fp-multilanguage-strings-search" class="regular-text" placeholder="' . esc_attr__( 'Cerca per testo o contesto…', 'fp-multilanguage' ) . '">';
        echo '</div>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="fp-multilanguage-strings-form">';
        wp_nonce_field( 'fp_multilanguage_save_strings' );
        echo '<input type="hidden" name="action" value="fp_multilanguage_save_strings">';

        echo '<table class="widefat fixed striped" id="fp-multilanguage-strings-table">';
        echo '<thead><tr>';
        echo '<th style="width:15%;">' . esc_html__( 'Chiave', 'fp-multilanguage' ) . '</th>';
        echo '<th style="width:30%;">' . esc_html__( 'Originale', 'fp-multilanguage' ) . '</th>';
        echo '<th style="width:20%;">' . esc_html__( 'Contesto', 'fp-multilanguage' ) . '</th>';

        foreach ( $languages as $language ) {
            echo '<th>' . esc_html( sprintf( __( 'Traduzione (%s)', 'fp-multilanguage' ), strtoupper( $language ) ) ) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ( $strings as $string ) {
            $key          = isset( $string['key'] ) ? (string) $string['key'] : '';
            $original     = isset( $string['original'] ) ? (string) $string['original'] : '';
            $context      = isset( $string['context'] ) ? (string) $string['context'] : '';
            $translations = isset( $string['translations'] ) && is_array( $string['translations'] ) ? $string['translations'] : array();

            $contextDisplay = $context !== '' ? str_replace( '|', ' → ', $context ) : '—';

            echo '<tr>';
            echo '<td><code>' . esc_html( $key ) . '</code></td>';
            echo '<td>' . ( $original !== '' ? nl2br( esc_html( $original ) ) : '<span class="description">' . esc_html__( 'Non disponibile', 'fp-multilanguage' ) . '</span>' ) . '</td>';
            echo '<td>' . ( $contextDisplay !== '—' ? esc_html( $contextDisplay ) : '&#8212;' ) . '</td>';

            foreach ( $languages as $language ) {
                $value = isset( $translations[ $language ] ) ? (string) $translations[ $language ] : '';
                $rows  = max( 2, min( 6, substr_count( $value, "\n" ) + 1 ) );

                echo '<td>';
                echo '<textarea class="large-text code" name="strings[' . esc_attr( $key ) . '][' . esc_attr( $language ) . ']" rows="' . esc_attr( (string) $rows ) . '">' . esc_textarea( $value ) . '</textarea>';
                echo '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p id="fp-multilanguage-strings-empty" class="description" style="display:none;margin-top:1em;">' . esc_html__( 'Nessuna stringa corrisponde ai criteri di ricerca.', 'fp-multilanguage' ) . '</p>';
        submit_button( __( 'Salva traduzioni', 'fp-multilanguage' ) );
        echo '</form>';
        echo '</div>';
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            if ( function_exists( 'wp_die' ) ) {
                wp_die( esc_html__( 'Non hai i permessi per aggiornare le traduzioni manuali.', 'fp-multilanguage' ) );
            }

            return;
        }

        check_admin_referer( 'fp_multilanguage_save_strings' );

        $submitted = isset( $_POST['strings'] ) ? wp_unslash( $_POST['strings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! is_array( $submitted ) ) {
            $submitted = array();
        }

        $updates = 0;

        foreach ( $submitted as $key => $translations ) {
            $normalizedKey = sanitize_key( (string) $key );
            if ( '' === $normalizedKey || ! is_array( $translations ) ) {
                continue;
            }

            foreach ( $translations as $language => $value ) {
                $normalizedLanguage = sanitize_key( (string) $language );
                if ( '' === $normalizedLanguage ) {
                    continue;
                }

                $stringValue = is_scalar( $value ) ? (string) $value : '';
                $this->repository->update_manual_string( $normalizedKey, $normalizedLanguage, $stringValue );
                ++$updates;
            }
        }

        $this->logger->info(
            'Manual strings updated via admin page.',
            array(
                'updates' => $updates,
            )
        );

        $redirect = add_query_arg(
            array(
                'page'            => 'fp-multilanguage-strings',
                'strings-updated' => '1',
            ),
            admin_url( 'options-general.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * @param array<string, array<string, mixed>> $strings
     *
     * @return array<int, string>
     */
    private function get_manual_string_languages( array $strings ): array {
        $languages = array();

        foreach ( (array) $this->repository->get_target_languages() as $language ) {
            $languages[] = (string) $language;
        }

        $fallback = $this->repository->get_fallback_language();
        if ( $fallback !== '' ) {
            $languages[] = $fallback;
        }

        foreach ( $strings as $string ) {
            if ( empty( $string['translations'] ) || ! is_array( $string['translations'] ) ) {
                continue;
            }

            foreach ( $string['translations'] as $language => $value ) {
                unset( $value );
                $languages[] = (string) $language;
            }
        }

        $languages = array_filter(
            array_map(
                static function ( $language ): string {
                    return strtolower( trim( (string) $language ) );
                },
                $languages
            )
        );

        $source = strtolower( $this->repository->get_source_language() );
        $languages = array_filter(
            $languages,
            static function ( string $language ) use ( $source ): bool {
                return $language !== '' && $language !== $source;
            }
        );

        $unique = array();
        foreach ( $languages as $language ) {
            if ( in_array( $language, $unique, true ) ) {
                continue;
            }

            $unique[] = $language;
        }

        return $unique;
    }
}
