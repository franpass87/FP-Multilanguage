<?php
/**
 * SEO Sitemap Builder - Builds sitemap XML.
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
 * Builds sitemap XML markup.
 *
 * @since 0.10.0
 */
class SitemapBuilder {
	/**
	 * Build sitemap XML markup.
	 *
	 * @since 0.10.0
	 *
	 * @param array $entries Sitemap entries.
	 * @return string
	 */
	public function build_xml( array $entries ): string {
		$charset = $this->get_charset();

		$lines   = array();
		$lines[] = '<?xml version="1.0" encoding="' . esc_html( $charset ) . '"?>';
		$lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		foreach ( $entries as $entry ) {
			if ( empty( $entry['loc'] ) ) {
				continue;
			}

			$lines[] = '\t<url>';
			$lines[] = '\t\t<loc>' . esc_url( $entry['loc'] ) . '</loc>';

			if ( ! empty( $entry['lastmod'] ) ) {
				$lines[] = '\t\t<lastmod>' . esc_html( gmdate( 'c', (int) $entry['lastmod'] ) ) . '</lastmod>';
			}

			$lines[] = '\t</url>';
		}

		$lines[] = '</urlset>';

		return implode( "\n", $lines );
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















