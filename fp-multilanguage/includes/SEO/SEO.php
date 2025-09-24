<?php
namespace FPMultilanguage\SEO;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationService;
use WP_Post;

class SEO {

	private const META_KEY = '_fp_multilanguage_seo';

	private const SLUG_INDEX_OPTION = 'fp_multilanguage_slug_index';

	private Settings $settings;

	private TranslationService $translationService;

	private PostTranslationManager $postTranslationManager;

	private Logger $logger;

	public function __construct( Settings $settings, TranslationService $translationService, PostTranslationManager $postTranslationManager, Logger $logger ) {
		$this->settings               = $settings;
		$this->translationService     = $translationService;
		$this->postTranslationManager = $postTranslationManager;
		$this->logger                 = $logger;
	}

	public function register(): void {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_meta' ), 20, 2 );
			add_action( 'wp_head', array( $this, 'render_meta_tags' ), 1 );
			add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ) );
			add_filter( 'wp_sitemaps_posts_entry', array( $this, 'filter_sitemap_entry' ), 10, 3 );
			add_filter( 'wpseo_locale', array( $this, 'filter_wpseo_locale' ) );
			add_filter( 'wpseo_canonical', array( $this, 'filter_wpseo_canonical' ) );
			add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
			add_filter( 'request', array( $this, 'resolve_slug_request' ) );
			add_action( 'delete_post', array( $this, 'handle_post_delete' ) );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'fp-multilanguage-seo',
			__( 'SEO multilingua', 'fp-multilanguage' ),
			array( $this, 'render_meta_box' ),
			array( 'post', 'page' ),
			'normal',
			'default'
		);
	}

	public function render_meta_box( WP_Post $post ): void {
		$meta      = $this->get_meta( $post->ID );
		$languages = array_unique(
			array_merge(
				array(
					Settings::get_source_language(),
				),
				Settings::get_target_languages()
			)
		);

		wp_nonce_field( 'fp_multilanguage_seo_meta', 'fp_multilanguage_seo_nonce' );

		echo '<p>' . esc_html__( 'Personalizza title, description e slug per ogni lingua.', 'fp-multilanguage' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Lingua', 'fp-multilanguage' ) . '</th>';
		echo '<th>' . esc_html__( 'Meta title', 'fp-multilanguage' ) . '</th>';
		echo '<th>' . esc_html__( 'Meta description', 'fp-multilanguage' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'fp-multilanguage' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $languages as $language ) {
			$title       = $meta['title'][ $language ] ?? '';
			$description = $meta['description'][ $language ] ?? '';
			$slug        = $meta['slug'][ $language ] ?? '';
			echo '<tr>';
			echo '<td><code>' . esc_html( $language ) . '</code></td>';
			echo '<td><input type="text" class="widefat" name="fp_multilanguage_seo[title][' . esc_attr( $language ) . ']" value="' . esc_attr( $title ) . '" maxlength="160"></td>';
			echo '<td><textarea class="widefat" name="fp_multilanguage_seo[description][' . esc_attr( $language ) . ']" rows="2" maxlength="320">' . esc_textarea( $description ) . '</textarea></td>';
			echo '<td><input type="text" class="widefat" name="fp_multilanguage_seo[slug][' . esc_attr( $language ) . ']" value="' . esc_attr( $slug ) . '"></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function save_meta( int $postId, WP_Post $post ): void {
		if ( ! isset( $_POST['fp_multilanguage_seo_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_multilanguage_seo_nonce'] ) ), 'fp_multilanguage_seo_meta' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}

		$raw = isset( $_POST['fp_multilanguage_seo'] ) && is_array( $_POST['fp_multilanguage_seo'] )
			? wp_unslash( $_POST['fp_multilanguage_seo'] )
			: array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$sanitized = array(
			'title'       => array(),
			'description' => array(),
			'slug'        => array(),
		);

		foreach ( array( 'title', 'description', 'slug' ) as $field ) {
			if ( ! isset( $raw[ $field ] ) || ! is_array( $raw[ $field ] ) ) {
				continue;
			}

			foreach ( $raw[ $field ] as $language => $value ) {
				$language = strtolower( sanitize_key( $language ) );
				if ( $language === '' ) {
					continue;
				}

				if ( $field === 'description' ) {
					$sanitized[ $field ][ $language ] = sanitize_textarea_field( $value );
				} else {
					$sanitized[ $field ][ $language ] = sanitize_text_field( $value );
				}
			}
		}

				update_post_meta( $postId, self::META_KEY, $sanitized );
				$this->update_slug_index( $postId, $sanitized['slug'] );
	}

	public function handle_post_delete( int $postId ): void {
			$this->remove_from_slug_index( $postId );
	}

	public function render_meta_tags(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$options = Settings::get_options();
		if ( empty( $options['seo']['hreflang'] ) && empty( $options['seo']['canonical'] ) && empty( $options['seo']['open_graph'] ) ) {
			return;
		}

		$meta            = $this->get_meta( $post->ID );
		$currentLanguage = CurrentLanguage::resolve();
		$translations    = $this->postTranslationManager->get_post_translations( $post->ID );
		$languageUrls    = $this->get_language_urls( $post, $meta );

		if ( ! empty( $options['seo']['canonical'] ) && isset( $languageUrls[ $currentLanguage ] ) ) {
			echo '<link rel="canonical" href="' . esc_url( $languageUrls[ $currentLanguage ] ) . '" />' . "\n";
		}

		if ( ! empty( $options['seo']['hreflang'] ) ) {
			foreach ( $languageUrls as $lang => $url ) {
				echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $url ) . '" />' . "\n";
			}
			$source = Settings::get_source_language();
			if ( isset( $languageUrls[ $source ] ) ) {
				echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $languageUrls[ $source ] ) . '" />' . "\n";
			}
		}

		$description = $meta['description'][ $currentLanguage ] ?? '';
		if ( $description === '' && isset( $translations[ $currentLanguage ]['excerpt'] ) ) {
			$description = wp_strip_all_tags( $translations[ $currentLanguage ]['excerpt'] );
		}
		if ( $description === '' ) {
				$excerpt_source = $post->post_excerpt;
			if ( $excerpt_source === '' ) {
						$excerpt_source = $post->post_content;
			}

				$description = wp_strip_all_tags( $excerpt_source );
		}

		echo '<meta name="description" content="' . esc_attr( wp_trim_words( $description, 55 ) ) . '" />' . "\n";

		if ( ! empty( $options['seo']['open_graph'] ) ) {
			echo '<meta property="og:locale" content="' . esc_attr( $currentLanguage ) . '" />' . "\n";
			echo '<meta property="og:title" content="' . esc_attr( $this->filter_document_title( get_the_title( $post ) ) ) . '" />' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( wp_trim_words( $description, 55 ) ) . '" />' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $languageUrls[ $currentLanguage ] ?? get_permalink( $post ) ) . '" />' . "\n";
		}
	}

	public function filter_document_title( string $title ): string {
		if ( ! is_singular() ) {
			return $title;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $title;
		}

		$currentLanguage = CurrentLanguage::resolve();
		$meta            = $this->get_meta( $post->ID );
		$translations    = $this->postTranslationManager->get_post_translations( $post->ID );

		if ( isset( $meta['title'][ $currentLanguage ] ) && $meta['title'][ $currentLanguage ] !== '' ) {
			return $meta['title'][ $currentLanguage ];
		}

		if ( isset( $translations[ $currentLanguage ]['title'] ) && $translations[ $currentLanguage ]['title'] !== '' ) {
			return $translations[ $currentLanguage ]['title'];
		}

		$source = Settings::get_source_language();
		if ( $currentLanguage !== $source ) {
			$translated = $this->translationService->translate_text( $post->post_title, $source, $currentLanguage );
			if ( $translated !== '' ) {
				return $translated;
			}
		}

		return $title;
	}

	public function filter_wpseo_locale( string $locale ): string {
		$language = CurrentLanguage::resolve();
		if ( $language !== '' ) {
			return $language;
		}

		return $locale;
	}

	public function filter_wpseo_canonical( string $url ): string {
		if ( ! is_singular() ) {
			return $url;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $url;
		}

		$languageUrls = $this->get_language_urls( $post, $this->get_meta( $post->ID ) );
		$language     = CurrentLanguage::resolve();

		return $languageUrls[ $language ] ?? $url;
	}

	public function filter_sitemap_entry( array $entry, WP_Post $post, string $postType ): array {
			unset( $postType );

			$languageUrls = $this->get_language_urls( $post, $this->get_meta( $post->ID ) );
		if ( ! empty( $languageUrls ) ) {
			$entry['alternates'] = array();
			foreach ( $languageUrls as $lang => $url ) {
						$entry['alternates'][] = array(
							'rel'      => 'alternate',
							'hreflang' => $lang,
							'href'     => $url,
						);
			}
		}

			return $entry;
	}

	public function filter_robots_txt( string $output, bool $is_public ): string {
		if ( ! $is_public ) {
				return $output;
		}

		if ( strpos( $output, 'Sitemap:' ) === false ) {
				$output .= "\n# FP Multilanguage";
				$output .= "\nSitemap: " . home_url( '/wp-sitemap.xml' );
		}

			return $output;
	}

	private function get_meta( int $postId ): array {
		$stored = get_post_meta( $postId, self::META_KEY, true );
		if ( ! is_array( $stored ) ) {
			return array(
				'title'       => array(),
				'description' => array(),
				'slug'        => array(),
			);
		}

		return wp_parse_args(
			$stored,
			array(
				'title'       => array(),
				'description' => array(),
				'slug'        => array(),
			)
		);
	}

	private function get_language_urls( WP_Post $post, array $meta ): array {
			$urls         = array();
			$source       = Settings::get_source_language();
			$translations = $this->postTranslationManager->get_post_translations( $post->ID );

			$urls[ $source ] = get_permalink( $post );
		if ( isset( $meta['slug'][ $source ] ) && $meta['slug'][ $source ] !== '' ) {
				$sourceSlug = $this->normalize_slug( $meta['slug'][ $source ] );
			if ( $sourceSlug !== '' ) {
				$urls[ $source ] = trailingslashit( home_url( $sourceSlug ) );
			}
		}

		foreach ( $translations as $language => $data ) {
			if ( ! isset( $data['content'] ) || $data['content'] === '' ) {
					continue;
			}

				$slug = $meta['slug'][ $language ] ?? '';
				$slug = $this->normalize_slug( $slug );
			if ( $slug !== '' ) {
					$urls[ $language ] = trailingslashit( home_url( $slug ) );
			} else {
					$urls[ $language ] = add_query_arg( 'fp_lang', $language, get_permalink( $post ) );
			}
		}

			return $urls;
	}

	public function resolve_slug_request( array $queryVars ): array {
		if ( is_admin() ) {
				return $queryVars;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || isset( $queryVars['rest_route'] ) ) {
				return $queryVars;
		}

		if ( isset( $queryVars['p'] ) || isset( $queryVars['page_id'] ) ) {
				return $queryVars;
		}

			$path = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$path = (string) $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

			$parsed     = (string) parse_url( $path, PHP_URL_PATH );
			$normalized = trim( $parsed, '/' );
		if ( $normalized === '' || strpos( $normalized, 'wp-admin' ) === 0 || strpos( $normalized, 'wp-json' ) === 0 ) {
				return $queryVars;
		}

			$index  = self::get_slug_index();
			$byPath = is_array( $index['by_path'] ?? null ) ? $index['by_path'] : array();
		if ( ! isset( $byPath[ $normalized ] ) ) {
				return $queryVars;
		}

			$mapping  = $byPath[ $normalized ];
			$postId   = (int) ( $mapping['post_id'] ?? 0 );
			$language = isset( $mapping['language'] ) ? sanitize_key( (string) $mapping['language'] ) : '';

		if ( $postId <= 0 ) {
				return $queryVars;
		}

			$queryVars['p']       = $postId;
			$queryVars['page_id'] = $postId;

		if ( $language !== '' ) {
				$queryVars['fp_lang'] = $language;
		}

			unset( $queryVars['name'], $queryVars['pagename'] );

			return $queryVars;
	}

	public static function get_language_slugs( int $postId ): array {
			$index  = self::get_slug_index();
			$byPost = is_array( $index['by_post'] ?? null ) ? $index['by_post'] : array();

		if ( isset( $byPost[ $postId ] ) && is_array( $byPost[ $postId ] ) ) {
				return $byPost[ $postId ];
		}

			return array();
	}

	private static function get_slug_index(): array {
			$stored = get_option( self::SLUG_INDEX_OPTION, array() );
		if ( ! is_array( $stored ) ) {
				return array(
					'by_path' => array(),
					'by_post' => array(),
				);
		}

			$defaults = array(
				'by_path' => array(),
				'by_post' => array(),
			);

			return wp_parse_args( $stored, $defaults );
	}

	private function update_slug_index( int $postId, array $slugs ): void {
			$index = self::get_slug_index();

		foreach ( $index['by_path'] as $path => $data ) {
			if ( (int) ( $data['post_id'] ?? 0 ) === $postId ) {
				unset( $index['by_path'][ $path ] );
			}
		}

			unset( $index['by_post'][ $postId ] );

		foreach ( $slugs as $language => $slug ) {
				$languageKey = sanitize_key( (string) $language );
				$normalized  = $this->normalize_slug( (string) $slug );

			if ( $languageKey === '' || $normalized === '' ) {
					continue;
			}

				$index['by_path'][ $normalized ] = array(
					'post_id'  => $postId,
					'language' => $languageKey,
				);

				if ( ! isset( $index['by_post'][ $postId ] ) ) {
						$index['by_post'][ $postId ] = array();
				}

				$index['by_post'][ $postId ][ $languageKey ] = $normalized;
		}

			update_option( self::SLUG_INDEX_OPTION, $index );
	}

	private function remove_from_slug_index( int $postId ): void {
			$index   = self::get_slug_index();
			$changed = false;

		foreach ( $index['by_path'] as $path => $data ) {
			if ( (int) ( $data['post_id'] ?? 0 ) === $postId ) {
				unset( $index['by_path'][ $path ] );
				$changed = true;
			}
		}

		if ( isset( $index['by_post'][ $postId ] ) ) {
				unset( $index['by_post'][ $postId ] );
				$changed = true;
		}

		if ( $changed ) {
				update_option( self::SLUG_INDEX_OPTION, $index );
		}
	}

	private function normalize_slug( string $slug ): string {
			$slug = trim( $slug );

			$slug = trim( $slug, '/' );

			return $slug;
	}
}
