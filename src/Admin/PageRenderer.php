<?php
/**
 * Admin page renderer - Handles rendering of admin page tabs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders admin page tabs and content.
 *
 * @since 0.10.0
 */
class PageRenderer {
    use ContainerAwareTrait;

    /**
     * Get settings instance.
     *
     * @return \FPML_Settings|mixed
     */
    protected function getSettings() {
        $container = $this->getContainer();
        if ( $container && $container->has( 'options' ) ) {
            return $container->get( 'options' );
        }
        // Fallback to singleton with null check
        if ( class_exists( '\FPML_Settings' ) ) {
            $settings = function_exists( 'fpml_get_options' ) ? fpml_get_options() : \FPML_Settings::instance();
            return $settings ? $settings : null;
        }
        return null;
    }
    /**
     * Render tab navigation.
     *
     * @since 0.10.0
     *
     * @param string $current_tab Current active tab.
     * @return void
     */
    public function render_tab_navigation( $current_tab ) {
        $tabs = array(
            'dashboard' => __( 'Dashboard', 'fp-multilanguage' ),
            'general' => __( 'Generale', 'fp-multilanguage' ),
            'content' => __( 'Contenuto', 'fp-multilanguage' ),
            'strings' => __( 'Stringhe', 'fp-multilanguage' ),
            'glossary' => __( 'Glossario', 'fp-multilanguage' ),
            'seo' => __( 'SEO', 'fp-multilanguage' ),
            'export' => __( 'Export/Import', 'fp-multilanguage' ),
            'compatibility' => __( 'Compatibilità', 'fp-multilanguage' ),
            'diagnostics' => __( 'Diagnostica', 'fp-multilanguage' ),
            'translations' => __( 'Traduzioni', 'fp-multilanguage' ),
        );

        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $tab_key => $tab_label ) {
            $active = ( $current_tab === $tab_key ) ? ' nav-tab-active' : '';
            $url = admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=' . $tab_key );
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $tab_label ) . '</a>';
        }
        echo '</nav>';
    }

    /**
     * Render tab content.
     *
     * @since 0.10.0
     *
     * @param string $tab Tab key.
     * @return void
     */
    public function render_tab_content( $tab ) {
        switch ( $tab ) {
            case 'general':
                $this->render_general_tab();
                break;
            case 'content':
                $this->render_content_tab();
                break;
            case 'strings':
                $this->render_strings_tab();
                break;
            case 'glossary':
                $this->render_glossary_tab();
                break;
            case 'seo':
                $this->render_seo_tab();
                break;
            case 'export':
                $this->render_export_tab();
                break;
            case 'compatibility':
                $this->render_compatibility_tab();
                break;
            case 'diagnostics':
                $this->render_diagnostics_tab();
                break;
            case 'translations':
                $this->render_translations_tab();
                break;
            default:
                $this->render_dashboard_tab();
                break;
        }
    }

    /**
     * Render diagnostics tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_diagnostics_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-diagnostics.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-diagnostics.php';
        } else {
            echo '<p>' . esc_html__( 'File diagnostiche non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render dashboard tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_dashboard_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-dashboard.php' ) ) {
            $stats = $this->get_dashboard_stats();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-dashboard.php';
        } else {
            echo '<p>' . esc_html__( 'File dashboard non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Get dashboard statistics.
     *
     * @since 0.10.0
     * @return array
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array(
            'translated_posts' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'monthly_cost' => 0,
            'weekly_count' => 0,
            'weekly_trend' => 0,
            'recent_errors' => array(),
            'api_key_set' => false,
        );
        
        $stats['translated_posts'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT meta_id) FROM {$wpdb->postmeta} WHERE meta_key = '_fpml_pair_id'"
        );
        
        $queue_table = $wpdb->prefix . 'FPML_queue';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$queue_table}'" ) === $queue_table ) {
            $stats['pending_jobs'] = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$queue_table} WHERE state = %s", 'pending' )
            );
            
            $stats['failed_jobs'] = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$queue_table} WHERE state = %s", 'failed' )
            );
            
            $stats['recent_errors'] = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$queue_table} WHERE state = %s ORDER BY updated_at DESC LIMIT 3", 'failed' )
            );
            
            $stats['weekly_count'] = (int) $wpdb->get_var(
                $wpdb->prepare( 
                    "SELECT COUNT(*) FROM {$queue_table} WHERE state = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                    'completed'
                )
            );
            
            $prev_week_count = (int) $wpdb->get_var(
                $wpdb->prepare( 
                    "SELECT COUNT(*) FROM {$queue_table} WHERE state = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
                    'completed'
                )
            );
            
            if ( $prev_week_count > 0 ) {
                $stats['weekly_trend'] = round( ( ( $stats['weekly_count'] - $prev_week_count ) / $prev_week_count ) * 100 );
            } else if ( $stats['weekly_count'] > 0 ) {
                $stats['weekly_trend'] = 100;
            }
        }
        
        $current_month = date( 'Y-m' );
        $stats['monthly_cost'] = (float) get_option( 'fpml_spent_' . $current_month, 0 );
        
        $settings = class_exists( '\FPML_Settings' ) ? \FPML_Settings::instance() : null;
        $options = $settings ? $settings->all() : array();
        $stats['api_key_set'] = ! empty( $options['openai_api_key'] );
        
        return $stats;
    }

    /**
     * Render general tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_general_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-general.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-general.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni generali non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render content tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_content_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-content.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-content.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni contenuto non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render strings tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_strings_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-strings.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-strings.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni stringhe non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render glossary tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_glossary_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-glossary.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-glossary.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni glossario non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render SEO tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_seo_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-seo.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-seo.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni SEO non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render export tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_export_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-export.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-export.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni export non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render compatibility tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_compatibility_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-plugin-compatibility.php' ) ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include \FPML_PLUGIN_DIR . 'admin/views/settings-plugin-compatibility.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni compatibilità non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }

    /**
     * Render translations tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_translations_tab() {
        if ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-site-parts.php' ) ) {
            include \FPML_PLUGIN_DIR . 'admin/views/settings-site-parts.php';
        } elseif ( file_exists( \FPML_PLUGIN_DIR . 'admin/views/settings-translations.php' ) ) {
            include \FPML_PLUGIN_DIR . 'admin/views/settings-translations.php';
        } else {
            echo '<p>' . esc_html__( 'File impostazioni traduzioni non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
}
















