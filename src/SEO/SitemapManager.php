<?php
/**
 * SEO Sitemap Manager - Handles sitemap generation and caching.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\SEO;

use FP\Multilanguage\SEO\Sitemap\SitemapConfig;
use FP\Multilanguage\SEO\Sitemap\SitemapCollector;
use FP\Multilanguage\SEO\Sitemap\SitemapBuilder;
use FP\Multilanguage\SEO\Sitemap\SitemapCache;
use FP\Multilanguage\SEO\Sitemap\SitemapRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages sitemap generation, caching, and integration with SEO plugins.
 *
 * @since 0.10.0
 * @since 0.10.0 Refactored to use modular components.
 */
class SitemapManager {
	/**
	 * Settings instance.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Language helper instance.
	 *
	 * @var \FPML_Language
	 */
	protected $language;

	/**
	 * Sitemap config.
	 *
	 * @since 0.10.0
	 *
	 * @var SitemapConfig
	 */
	protected SitemapConfig $config;

	/**
	 * Sitemap collector.
	 *
	 * @since 0.10.0
	 *
	 * @var SitemapCollector
	 */
	protected SitemapCollector $collector;

	/**
	 * Sitemap builder.
	 *
	 * @since 0.10.0
	 *
	 * @var SitemapBuilder
	 */
	protected SitemapBuilder $builder;

	/**
	 * Sitemap cache.
	 *
	 * @since 0.10.0
	 *
	 * @var SitemapCache
	 */
	protected SitemapCache $cache;

	/**
	 * Sitemap renderer.
	 *
	 * @since 0.10.0
	 *
	 * @var SitemapRenderer
	 */
	protected SitemapRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 *
	 * @param \FPML_Settings $settings Settings instance.
	 * @param \FPML_Language $language Language helper instance.
	 */
	public function __construct( $settings, $language ) {
		$this->settings = $settings;
		$this->language = $language;

		// Initialize modules
		$this->config = new SitemapConfig();
		$this->collector = new SitemapCollector( $language, $this->config );
		$this->builder = new SitemapBuilder();
		$this->cache = new SitemapCache();
		$this->renderer = new SitemapRenderer( $this->builder, $this->collector, $this->cache );
	}

	/**
	 * Maybe render the English sitemap when requested.
	 *
	 * @since 0.10.0
	 * @since 0.10.0 Delegates to SitemapRenderer.
	 *
	 * @return void
	 */
	public function maybe_render_sitemap(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->settings->get( 'sitemap_en', true ) ) {
			return;
		}

		$requested = get_query_var( '\FPML_sitemap' );

		if ( empty( $requested ) && isset( $_GET['\FPML_sitemap'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_key( wp_unslash( $_GET['\FPML_sitemap'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$requested = sanitize_key( (string) $requested );
		}

		if ( 'en' !== $requested ) {
			return;
		}

		$this->renderer->render();
	}

	/**
	 * Get sitemap last modification timestamp.
	 *
	 * @since 0.10.0
	 * @since 0.10.0 Delegates to SitemapCollector.
	 *
	 * @return int
	 */
	public function get_sitemap_lastmod_timestamp(): int {
		return $this->collector->get_sitemap_lastmod_timestamp();
	}

	/**
	 * Return sitemap absolute URL.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	protected function get_sitemap_url(): string {
		if ( ! $this->settings->get( 'sitemap_en', true ) ) {
			return '';
		}

		return home_url( '/sitemap-en.xml' );
	}

	/**
	 * Inject English sitemap entry into Yoast SEO sitemap index.
	 *
	 * @since 0.10.0
	 *
	 * @param string $content Sitemap XML.
	 * @return string
	 */
	public function inject_wpseo_sitemap_entry( string $content ): string {
		$content = (string) $content;
		$url     = $this->get_sitemap_url();

		if ( '' === $url || false === strpos( $content, '<sitemapindex' ) ) {
			return $content;
		}

		$lastmod = $this->collector->get_sitemap_lastmod_timestamp();
		$entry   = "\n\t<sitemap>\n\t\t<loc>" . esc_url( $url ) . "</loc>\n\t\t<lastmod>" . esc_html( gmdate( 'c', $lastmod ) ) . "</lastmod>\n\t</sitemap>\n";

		$insert_pos = strrpos( $content, '</sitemapindex>' );

		if ( false !== $insert_pos ) {
			$content = substr_replace( $content, $entry, $insert_pos, 0 );
		}

		return $content;
	}
}
