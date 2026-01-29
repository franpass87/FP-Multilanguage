<?php
/**
 * Admin page renderer - Handles rendering of admin page tabs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin\Pages;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\Admin\Admin;
use FP\Multilanguage\Admin\Contracts\PageInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders admin page tabs and content.
 *
 * @since 0.10.0
 */
class PageRenderer implements PageInterface {
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
     * Get the page slug.
     *
     * @return string Page slug.
     */
    public function getPageSlug(): string {
        return Admin::MENU_SLUG;
    }

    /**
     * Get the page title.
     *
     * @return string Page title.
     */
    public function getPageTitle(): string {
        return __( 'FP Multilanguage', 'fp-multilanguage' );
    }

    /**
     * Get view file path with fallback.
     *
     * @param string $view_name View filename.
     * @return string|null View path or null if not found.
     */
    private function get_view_path( $view_name ) {
        $new_path = \FPML_PLUGIN_DIR . 'src/Admin/Views/' . $view_name;
        $old_path = \FPML_PLUGIN_DIR . 'admin/views/' . $view_name;
        
        if ( file_exists( $new_path ) ) {
            return $new_path;
        }
        if ( file_exists( $old_path ) ) {
            return $old_path;
        }
        return null;
    }

    /**
     * Render the page.
     *
     * @since 0.10.0
     * @return void
     */
    public function render(): void {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $tabs = array(
            'dashboard' => __( 'Dashboard', 'fp-multilanguage' ),
            'general' => __( 'Generale', 'fp-multilanguage' ),
            'content' => __( 'Contenuto', 'fp-multilanguage' ),
            'strings' => __( 'Stringhe', 'fp-multilanguage' ),
            'glossary' => __( 'Glossario', 'fp-multilanguage' ),
            'seo' => __( 'SEO', 'fp-multilanguage' ),
            'export' => __( 'Export/Import', 'fp-multilanguage' ),
            'compatibility' => __( 'Compatibilità', 'fp-multilanguage' ),
            'translations' => __( 'Traduzioni', 'fp-multilanguage' ),
            'diagnostics' => __( 'Diagnostica', 'fp-multilanguage' ),
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $this->getPageTitle() ) . '</h1>';

        // Render tabs
        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $tab_key => $tab_label ) {
            $active_class = ( $active_tab === $tab_key ) ? ' nav-tab-active' : '';
            $url = add_query_arg( array( 'page' => $this->getPageSlug(), 'tab' => $tab_key ), admin_url( 'admin.php' ) );
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html( $tab_label ) . '</a>';
        }
        echo '</nav>';

        // Render tab content
        echo '<div class="tab-content">';
        switch ( $active_tab ) {
            case 'dashboard':
                $this->render_dashboard_tab();
                break;
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
            case 'translations':
                $this->render_translations_tab();
                break;
            case 'diagnostics':
                $this->render_diagnostics_tab();
                break;
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render tab navigation (for backward compatibility with Admin.php).
     *
     * @since 0.10.0
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
            'translations' => __( 'Traduzioni', 'fp-multilanguage' ),
            'diagnostics' => __( 'Diagnostica', 'fp-multilanguage' ),
        );

        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $tab_key => $tab_label ) {
            $active = ( $current_tab === $tab_key ) ? ' nav-tab-active' : '';
            $url = admin_url( 'admin.php?page=' . $this->getPageSlug() . '&tab=' . $tab_key );
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $tab_label ) . '</a>';
        }
        echo '</nav>';
    }

    /**
     * Render tab content (for backward compatibility with Admin.php).
     *
     * @since 0.10.0
     * @param string $tab Tab key.
     * @return void
     */
    public function render_tab_content( $tab ) {
        echo '<div class="tab-content">';
        switch ( $tab ) {
            case 'dashboard':
                $this->render_dashboard_tab();
                break;
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
            case 'translations':
                $this->render_translations_tab();
                break;
            case 'diagnostics':
                $this->render_diagnostics_tab();
                break;
            default:
                $this->render_dashboard_tab();
                break;
        }
        echo '</div>';
    }

    /**
     * Render diagnostics tab.
     *
     * @since 0.10.0
     * @return void
     */
    private function render_diagnostics_tab() {
        $view_path = $this->get_view_path( 'settings-diagnostics.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            // FIX CRITICO: Aggiunto error handling per evitare fatal error
            try {
                $plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
            } catch ( \Exception $e ) {
                error_log( 'FPML PageRenderer: Error loading plugin instance - ' . $e->getMessage() );
                $plugin = null;
            } catch ( \Error $e ) {
                error_log( 'FPML PageRenderer: Fatal error loading plugin instance - ' . $e->getMessage() );
                $plugin = null;
            }
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-dashboard.php' );
        if ( $view_path ) {
            $stats = $this->get_dashboard_stats();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-general.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-content.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-strings.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-glossary.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-seo.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-export.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-plugin-compatibility.php' );
        if ( $view_path ) {
            $container = $this->getContainer();
            $settings = $this->getSettings();
            $options = $settings ? $settings->all() : array();
            include $view_path;
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
        $view_path = $this->get_view_path( 'settings-site-parts.php' );
        if ( $view_path ) {
            include $view_path;
        } else {
            $view_path = $this->get_view_path( 'settings-translations.php' );
            if ( $view_path ) {
                include $view_path;
            } else {
                echo '<p>' . esc_html__( 'File impostazioni traduzioni non trovato.', 'fp-multilanguage' ) . '</p>';
            }
        }
    }
}
