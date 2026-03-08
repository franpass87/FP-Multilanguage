<?php
/**
 * Filter Helper - Provides helper methods for temporarily removing/restoring filters.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Permalink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for managing filter removal and restoration.
 *
 * Temporarily removes WordPress filters that would cause infinite recursion
 * when permalink/URL filters call get_permalink() or home_url() internally.
 *
 * @since 0.10.0
 */
class FilterHelper {
	/**
	 * The permalink filter object (registered on post_link / page_link / post_type_link).
	 *
	 * @var object|null
	 */
	protected $permalink_filter_obj = null;

	/**
	 * The URL filter object (registered on home_url / site_url).
	 *
	 * @var object|null
	 */
	protected $url_filter_obj = null;

	/**
	 * Set the permalink filter object used for post_link / page_link / post_type_link.
	 *
	 * @param object $obj Object whose filter_translation_permalink method is hooked.
	 * @return void
	 */
	public function set_permalink_filter( $obj ) {
		$this->permalink_filter_obj = $obj;
	}

	/**
	 * Set the URL filter object used for home_url / site_url.
	 *
	 * @param object $obj Object whose filter_home_url_for_en / filter_site_url_for_en methods are hooked.
	 * @return void
	 */
	public function set_url_filter( $obj ) {
		$this->url_filter_obj = $obj;
	}

	/**
	 * Remove permalink filters temporarily to avoid recursion.
	 *
	 * @since 0.10.0
	 * @return void
	 */
	public function remove_permalink_filters() {
		if ( ! $this->permalink_filter_obj ) {
			return;
		}
		remove_filter( 'post_link', array( $this->permalink_filter_obj, 'filter_translation_permalink' ), 10 );
		remove_filter( 'page_link', array( $this->permalink_filter_obj, 'filter_translation_permalink' ), 10 );
		remove_filter( 'post_type_link', array( $this->permalink_filter_obj, 'filter_translation_permalink' ), 10 );
	}

	/**
	 * Restore permalink filters after temporary removal.
	 *
	 * @since 0.10.0
	 * @return void
	 */
	public function restore_permalink_filters() {
		if ( ! $this->permalink_filter_obj ) {
			return;
		}
		add_filter( 'post_link', array( $this->permalink_filter_obj, 'filter_translation_permalink' ), 10, 2 );
		add_filter( 'page_link', array( $this->permalink_filter_obj, 'filter_translation_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( $this->permalink_filter_obj, 'filter_translation_permalink' ), 10, 2 );
	}

	/**
	 * Remove URL filters temporarily to avoid recursion in home_url() calls.
	 *
	 * @since 0.10.0
	 * @return void
	 */
	public function remove_url_filters() {
		if ( ! $this->url_filter_obj ) {
			return;
		}
		remove_filter( 'home_url', array( $this->url_filter_obj, 'filter_home_url_for_en' ), 10 );
		remove_filter( 'site_url', array( $this->url_filter_obj, 'filter_site_url_for_en' ), 10 );
	}

	/**
	 * Restore URL filters after temporary removal.
	 *
	 * @since 0.10.0
	 * @return void
	 */
	public function restore_url_filters() {
		if ( ! $this->url_filter_obj ) {
			return;
		}
		add_filter( 'home_url', array( $this->url_filter_obj, 'filter_home_url_for_en' ), 10, 2 );
		add_filter( 'site_url', array( $this->url_filter_obj, 'filter_site_url_for_en' ), 10, 2 );
	}
}
