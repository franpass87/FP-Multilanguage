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

	public const SLUG_INDEX_OPTION = 'fp_multilanguage_slug_index';

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
		add_action( 'delete_post', array( $this, 'cleanup_slug_index' ) );
		add_action( 'wp_head', array( $this, 'render_meta_tags' ), 1 );
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( $this, 'filter_sitemap_entry' ), 10, 3 );
		add_filter( 'wpseo_locale', array( $this, 'filter_wpseo_locale' ) );
		add_filter( 'wpseo_canonical', array( $this, 'filter_wpseo_canonical' ) );
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
		add_filter( 'request', array( $this, 'resolve_translated_slug' ) );
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

		$slugMap = array();

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
				} elseif ( $field === 'slug' ) {
					$sanitizedSlug = $this->sanitize_slug( (string) $value );
					if ( $sanitizedSlug !== '' ) {
						$sanitized[ $field ][ $language ] = $sanitizedSlug;
						$slugMap[ $language ]             = $sanitizedSlug;
					}
				} else {
					$sanitized[ $field ][ $language ] = sanitize_text_field( $value );
				}
			}
		}

		update_post_meta( $postId, self::META_KEY, $sanitized );

		if ( ! empty( $sanitized['slug'] ) ) {
			$this->update_slug_index( $postId, $slugMap );
		} else {
			$this->cleanup_slug_index( $postId );
		}
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


	public function resolve_translated_slug( array $query_vars ): array {
		if ( is_admin() ) {
			return $query_vars;
		}

		if ( isset( $query_vars['p'] ) || isset( $query_vars['page_id'] ) || isset( $query_vars['attachment'] ) ) {
			return $query_vars;
		}

		$path = $this->extract_request_path( $query_vars );
		if ( $path === '' ) {
			return $query_vars;
		}

		$index = $this->get_slug_index();
		if ( empty( $index ) ) {
			return $query_vars;
		}

		$mapping = $index[ $path ] ?? null;
		if ( null === $mapping && preg_match( '#^(.*?)/(?:page/\d+|feed(?:/.*)?)$#', $path, $matches ) ) {
			$base = rtrim( (string) $matches[1], '/' );
			if ( isset( $index[ $base ] ) ) {
				$mapping = $index[ $base ];
			}
		}

		if ( ! is_array( $mapping ) ) {
			return $query_vars;
		}

		$postId   = isset( $mapping['post_id'] ) ? (int) $mapping['post_id'] : 0;
		$language = isset( $mapping['language'] ) ? sanitize_key( (string) $mapping['language'] ) : '';

		if ( $postId <= 0 ) {
			return $query_vars;
		}

		$query_vars['p']        = $postId;
		$query_vars['page_id']  = $postId;
		$query_vars['name']     = '';
		$query_vars['pagename'] = '';

		if ( $language !== '' ) {
			$query_vars['fp_lang'] = $language;
		}

		return $query_vars;
	}

	public function cleanup_slug_index( int $postId ): void {
		$index = $this->get_slug_index();
		if ( empty( $index ) ) {
			return;
		}

		foreach ( $index as $slug => $data ) {
			if ( isset( $data['post_id'] ) && (int) $data['post_id'] === $postId ) {
				unset( $index[ $slug ] );
			}
		}

		update_option( self::SLUG_INDEX_OPTION, $index );
	}

	private function update_slug_index( int $postId, array $slugs ): void {
		$index = $this->get_slug_index();

		foreach ( $index as $slug => $data ) {
			if ( isset( $data['post_id'] ) && (int) $data['post_id'] === $postId ) {
				unset( $index[ $slug ] );
			}
		}

		foreach ( $slugs as $language => $slug ) {
			$normalized = $this->sanitize_slug( $slug );
			if ( $normalized === '' ) {
				continue;
			}

			$index[ $normalized ] = array(
				'post_id'  => $postId,
				'language' => sanitize_key( $language ),
			);
		}

		update_option( self::SLUG_INDEX_OPTION, $index );
	}

	private function get_slug_index(): array {
		$stored = get_option( self::SLUG_INDEX_OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	private function extract_request_path( array $query_vars ): string {
		if ( isset( $query_vars['pagename'] ) && is_string( $query_vars['pagename'] ) && $query_vars['pagename'] !== '' ) {
			return $this->sanitize_slug( $query_vars['pagename'] );
		}

		if ( isset( $query_vars['name'] ) && is_string( $query_vars['name'] ) && $query_vars['name'] !== '' ) {
			return $this->sanitize_slug( $query_vars['name'] );
		}

		$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( $requestUri === '' ) {
			return '';
		}

		$parts = explode( '?', $requestUri, 2 );
		$path  = $parts[0];

		return $this->sanitize_slug( $path );
	}

	private function sanitize_slug( string $value ): string {
		$slug = strtolower( $value );
		$slug = preg_replace( '#https?://[^/]+#', '', $slug );
		$slug = preg_replace( '#[^a-z0-9/_-]+#', '-', $slug );
		$slug = preg_replace( '#-+#', '-', (string) $slug );
		$slug = preg_replace( '#/{2,}#', '/', (string) $slug );
		$slug = trim( (string) $slug );
		$slug = trim( $slug, '-' );
		$slug = trim( $slug, '/' );

		return $slug;
	}

	private function get_language_urls( WP_Post $post, array $meta ): array {
		$urls         = array();
		$source       = Settings::get_source_language();
		$translations = $this->postTranslationManager->get_post_translations( $post->ID );

		$urls[ $source ] = get_permalink( $post );
		if ( isset( $meta['slug'][ $source ] ) && $meta['slug'][ $source ] !== '' ) {
			$urls[ $source ] = trailingslashit( home_url( $meta['slug'][ $source ] ) );
		}

		foreach ( $translations as $language => $data ) {
			if ( ! isset( $data['content'] ) || $data['content'] === '' ) {
				continue;
			}

			$slug = $meta['slug'][ $language ] ?? '';
			if ( $slug !== '' ) {
				$urls[ $language ] = trailingslashit( home_url( $slug ) );
			} else {
				$urls[ $language ] = add_query_arg( 'fp_lang', $language, get_permalink( $post ) );
			}
		}

		return $urls;
	}
}
