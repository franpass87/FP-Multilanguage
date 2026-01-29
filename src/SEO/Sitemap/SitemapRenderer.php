<?php
/**
 * SEO Sitemap Renderer - Renders sitemap output.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders sitemap output.
 *
 * @since 0.10.0
 */
class SitemapRenderer {
	/**
	 * Sitemap builder instance.
	 *
	 * @var SitemapBuilder
	 */
	protected SitemapBuilder $builder;

	/**
	 * Sitemap collector instance.
	 *
	 * @var SitemapCollector
	 */
	protected SitemapCollector $collector;

	/**
	 * Sitemap cache instance.
	 *
	 * @var SitemapCache
	 */
	protected SitemapCache $cache;

	/**
	 * Constructor.
	 *
	 * @param SitemapBuilder   $builder   Sitemap builder instance.
	 * @param SitemapCollector $collector Sitemap collector instance.
	 * @param SitemapCache     $cache     Sitemap cache instance.
	 */
	public function __construct( SitemapBuilder $builder, SitemapCollector $collector, SitemapCache $cache ) {
		$this->builder = $builder;
		$this->collector = $collector;
		$this->cache = $cache;
	}

	/**
	 * Render sitemap.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function render(): void {
		$charset = $this->get_charset();

		// Try to get from cache
		$xml = $this->cache->get_cached();

		if ( null === $xml ) {
			// Try to acquire lock
			if ( $this->cache->acquire_lock() ) {
				// Build sitemap
				$entries = $this->collector->collect_entries();
				$xml = $this->builder->build_xml( $entries );
				$this->cache->cache( $xml );
				$this->cache->release_lock();
			} else {
				// Wait for lock and get cached result
				$xml = $this->cache->wait_for_lock();
				
				if ( null === $xml ) {
					// Fallback: build without cache
					$entries = $this->collector->collect_entries();
					$xml = $this->builder->build_xml( $entries );
				}
			}
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=' . $charset );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Get charset.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	protected function get_charset(): string {
		$charset = get_bloginfo( 'charset' );

		if ( ! $charset ) {
			$charset = 'UTF-8';
		}

		$charset = preg_replace( '/[^a-zA-Z0-9\-]/', '', $charset );
		
		if ( null === $charset || '' === $charset ) {
			$charset = 'UTF-8';
		}

		return $charset;
	}
}















