<?php
/**
 * Salient Theme Integration - Enhanced.
 *
 * Complete support for Salient Theme meta fields:
 * - Page headers & titles
 * - Portfolio projects
 * - Nectar sliders
 * - Post formats (quote, link, video, audio, gallery)
 * - Page builder rows
 * - Navigation settings
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 * @updated 0.9.0 - Complete meta fields coverage
 */

namespace FP\Multilanguage\Integrations;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SalientThemeSupport {
	use ContainerAwareTrait;
	protected static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger
	 */
	protected $logger;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		if ( ! $this->is_salient_active() ) {
			return;
		}

		// Initialize logger
		$container = $this->getContainer();
		if ( $container && $container->has( 'logger' ) ) {
			$this->logger = $container->get( 'logger' );
		} elseif ( class_exists( '\FP\Multilanguage\Logger' ) ) {
			$this->logger = fpml_get_logger();
		}

		add_filter( '\FPML_meta_whitelist', array( $this, 'add_salient_meta' ) );
		add_filter( '\FPML_translatable_post_types', array( $this, 'add_salient_cpts' ) );
		add_action( 'fpml_after_translation_saved', array( $this, 'sync_salient_settings' ), 10, 2 );
	}

	protected function is_salient_active() {
		return function_exists( 'nectar_get_theme_version' ) || 
		       defined( 'NECTAR_THEME_NAME' ) ||
		       'salient' === get_template();
	}

	/**
	 * Add Salient meta fields to translatable whitelist.
	 *
	 * @param array $meta_keys Current whitelist.
	 * @return array Extended whitelist.
	 */
	public function add_salient_meta( $meta_keys ) {
		$salient_translatable_meta = array(
			// === PAGE HEADER (translatable text) ===
			'_nectar_header_title',              // Page header title
			'_nectar_header_subtitle',           // Page header subtitle
			
			// === PORTFOLIO (translatable) ===
			'_nectar_portfolio_extra_content',   // Portfolio extra content (WYSIWYG)
			'_nectar_project_excerpt',           // Project excerpt/description
			'_nectar_portfolio_custom_grid_item_content', // Custom grid content
			
			// === POST FORMATS (translatable) ===
			'_nectar_quote',                     // Quote content
			'_nectar_quote_author',              // Quote author name
			'_nectar_video_embed',               // Video embed code (may have title)
			
			// === SLIDER/VIDEO (translatable captions) ===
			'_nectar_slider_caption',            // Slider caption text
			'_nectar_slider_caption_background', // Caption background
			
			// === CUSTOM SECTIONS (translatable) ===
			'_nectar_custom_section_title',      // Custom section title
			'_nectar_custom_section_content',    // Custom section content
			
			// === FOOTER (translatable) ===
			'_nectar_footer_custom_text',        // Footer custom text
		);

		return array_merge( $meta_keys, $salient_translatable_meta );
	}

	public function add_salient_cpts( $post_types ) {
		return array_merge( $post_types, array(
			'portfolio',
			'nectar_slider',
		) );
	}

	/**
	 * Sync Salient settings (non-translatable meta) from IT to EN.
	 *
	 * These are layout, styling, and configuration settings that should
	 * remain the same across both languages.
	 *
	 * @param int $translation_id EN post ID.
	 * @param int $source_id      IT post ID.
	 */
	public function sync_salient_settings( $translation_id, $source_id ) {
		$synced_count = 0;

		// === PAGE HEADER SETTINGS (styling, not text) ===
		$synced_count += $this->sync_page_header_settings( $translation_id, $source_id );

		// === PORTFOLIO SETTINGS (layout, not content) ===
		$synced_count += $this->sync_portfolio_settings( $translation_id, $source_id );

		// === POST FORMAT SETTINGS (media URLs, not text) ===
		$synced_count += $this->sync_post_format_settings( $translation_id, $source_id );

		// === PAGE BUILDER SETTINGS (fullscreen rows, etc) ===
		$synced_count += $this->sync_page_builder_settings( $translation_id, $source_id );

		// === NAVIGATION SETTINGS ===
		$synced_count += $this->sync_navigation_settings( $translation_id, $source_id );

		// Log sync
		if ( $this->logger ) {
			$this->logger->log(
				'info',
				'Salient Theme settings synced',
				array(
					'source_id'      => $source_id,
					'translation_id' => $translation_id,
					'meta_synced'    => $synced_count,
				)
			);
		}
	}

	/**
	 * Sync page header settings.
	 *
	 * @param int $translation_id EN post ID.
	 * @param int $source_id      IT post ID.
	 * @return int Number of fields synced.
	 */
	protected function sync_page_header_settings( $translation_id, $source_id ) {
		$fields = array(
			// Background & styling
			'_nectar_header_bg',                  // Header background image
			'_nectar_header_bg_color',            // Header background color
			'_nectar_header_font_color',          // Header font color
			'_nectar_header_bg_overlay_color',    // Overlay color
			'_nectar_header_bg_overlay_opacity',  // Overlay opacity
			
			// Layout & effects
			'_nectar_header_parallax',            // Parallax effect
			'_nectar_header_bg_height',           // Header height
			'_nectar_header_fullscreen',          // Fullscreen header
			'_nectar_page_header_alignment',      // Text alignment horizontal
			'_nectar_page_header_alignment_v',    // Text alignment vertical
			'_nectar_page_header_bg_alignment',   // Background alignment
			'_nectar_page_header_text-effect',    // Text effect
			'_nectar_header_box_roll',            // Box roll effect
			'_nectar_header_box_roll_disable_mobile', // Disable on mobile
			
			// Particles & animations
			'_nectar_particle_rotation_timing',   // Particle timing
			'_nectar_particle_disable_explosion', // Disable explosion
			
			// Video background
			'_nectar_slider_bg_type',             // BG type (video/image)
			'_nectar_media_upload_webm',          // WebM video
			'_nectar_media_upload_mp4',           // MP4 video
			'_nectar_media_upload_ogv',           // OGV video
			'_nectar_slider_preview_image',       // Video preview image
			'_nectar_canvas_shapes',              // Canvas shapes
			
			// Bottom effects
			'_nectar_header_bottom_shadow',       // Bottom shadow
			'_nectar_header_overlay',             // Overlay settings
		);

		return $this->copy_meta_fields( $fields, $translation_id, $source_id );
	}

	/**
	 * Sync portfolio settings.
	 *
	 * @param int $translation_id EN post ID.
	 * @param int $source_id      IT post ID.
	 * @return int Number of fields synced.
	 */
	protected function sync_portfolio_settings( $translation_id, $source_id ) {
		$fields = array(
			// Layout
			'_nectar_portfolio_item_layout',      // Full width layout
			'_nectar_portfolio_custom_grid_item', // Custom grid item
			'_nectar_portfolio_lightbox_only_grid_item', // Lightbox only
			
			// Images
			'_nectar_portfolio_custom_thumbnail',    // Custom thumbnail
			'_nectar_portfolio_secondary_thumbnail', // Secondary image
			'_nectar_hide_featured',                 // Hide featured image
			
			// Masonry
			'_portfolio_item_masonry_sizing',        // Masonry size
			'_portfolio_item_masonry_content_pos',   // Content position
			
			// Links & navigation
			'_nectar_external_project_url',          // External URL
			'nectar-metabox-portfolio-parent-override', // Parent override
			
			// Colors
			'_nectar_project_accent_color',          // Accent color
			'_nectar_project_title_color',           // Title color
			'_nectar_project_subtitle_color',        // Subtitle color
			
			// Advanced
			'_nectar_project_css_class',             // Custom CSS class
			'_nectar_portfolio_custom_video',        // Custom video URL
		);

		return $this->copy_meta_fields( $fields, $translation_id, $source_id );
	}

	/**
	 * Sync post format settings (gallery, video, audio, etc).
	 *
	 * @param int $translation_id EN post ID.
	 * @param int $source_id      IT post ID.
	 * @return int Number of fields synced.
	 */
	protected function sync_post_format_settings( $translation_id, $source_id ) {
		$fields = array(
			// Gallery
			'_nectar_gallery_slider',             // Enable gallery slider
			
			// Video
			'_nectar_video_m4v',                  // M4V video file
			'_nectar_video_ogv',                  // OGV video file
			'_nectar_video_poster',               // Video poster image
			// _nectar_video_embed is translatable (in whitelist)
			
			// Audio
			'_nectar_audio_mp3',                  // MP3 file
			'_nectar_audio_ogg',                  // OGG file
			
			// Link
			'_nectar_link',                       // Link URL
		);

		return $this->copy_meta_fields( $fields, $translation_id, $source_id );
	}

	/**
	 * Sync page builder settings (fullscreen rows, etc).
	 *
	 * @param int $translation_id EN post ID.
	 * @param int $source_id      IT post ID.
	 * @return int Number of fields synced.
	 */
	protected function sync_page_builder_settings( $translation_id, $source_id ) {
		$fields = array(
			// Fullscreen rows
			'_nectar_full_screen_rows',               // Enable fullscreen
			'_nectar_full_screen_rows_animation',     // Animation type
			'_nectar_full_screen_rows_animation_speed', // Animation speed
			'_nectar_full_screen_rows_overall_bg_color', // BG color
			'_nectar_full_screen_rows_anchors',       // URL anchors
			'_nectar_full_screen_rows_mobile_disable', // Disable mobile
			'_nectar_full_screen_rows_row_bg_animation', // Row BG animation
			'_nectar_full_screen_rows_dot_navigation', // Dot navigation
			'_nectar_full_screen_rows_content_overflow', // Content overflow
			'_nectar_full_screen_rows_footer',        // Footer display
		);

		return $this->copy_meta_fields( $fields, $translation_id, $source_id );
	}

	/**
	 * Sync navigation settings.
	 *
	 * @param int $translation_id EN post ID.
	 * @param int $source_id      IT post ID.
	 * @return int Number of fields synced.
	 */
	protected function sync_navigation_settings( $translation_id, $source_id ) {
		$fields = array(
			// Header transparency
			'_disable_transparent_header',            // Disable transparency
			'_force_transparent_header',              // Force transparency
			'_force_transparent_header_color',        // Transparent color
			
			// Navigation animation
			'_header_nav_entrance_animation',         // Entrance animation
			'_header_nav_entrance_animation_delay',   // Animation delay
			'_header_nav_entrance_animation_easing',  // Animation easing
		);

		return $this->copy_meta_fields( $fields, $translation_id, $source_id );
	}

	/**
	 * Helper to copy meta fields from source to translation.
	 *
	 * @param array $fields       Meta field keys to copy.
	 * @param int   $target_id    Target post ID.
	 * @param int   $source_id    Source post ID.
	 * @return int Number of fields copied.
	 */
	protected function copy_meta_fields( $fields, $target_id, $source_id ) {
		$count = 0;

		foreach ( $fields as $meta_key ) {
			$value = get_post_meta( $source_id, $meta_key, true );
			
			if ( '' !== $value && false !== $value && null !== $value ) {
				update_post_meta( $target_id, $meta_key, $value );
				$count++;
			}
		}

		return $count;
	}
}

