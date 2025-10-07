<?php
/**
 * SEO Auto-optimization per traduzioni (Feature Killer #3).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ottimizza automaticamente i meta SEO per le traduzioni.
 *
 * @since 0.4.0
 */
class FPML_SEO_Optimizer {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_SEO_Optimizer|null
	 */
	protected static $instance = null;

	/**
	 * Settings reference.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Logger reference.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_SEO_Optimizer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->settings = FPML_Settings::instance();
		$this->logger   = FPML_Logger::instance();

		// Hook dopo la traduzione del post.
		add_action( 'fpml_post_translated', array( $this, 'optimize_seo' ), 20, 4 );

		// Meta box per preview SEO.
		add_action( 'add_meta_boxes', array( $this, 'add_seo_preview_meta_box' ) );
	}

	/**
	 * Ottimizza i meta SEO dopo la traduzione.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 * @param string  $field       Campo tradotto.
	 * @param string  $value       Valore tradotto.
	 * @param object  $job         Job della coda.
	 *
	 * @return void
	 */
	public function optimize_seo( $target_post, $field, $value, $job ) {
		// Solo quando viene tradotto il contenuto principale.
		if ( 'post_content' !== $field && 'post_title' !== $field ) {
			return;
		}

		// Controlla se l'ottimizzazione è abilitata.
		if ( ! $this->settings || ! $this->settings->get( 'auto_optimize_seo', true ) ) {
			return;
		}

		// Genera meta description se mancante.
		$this->generate_meta_description( $target_post );

		// Genera focus keyword basata sul titolo.
		$this->generate_focus_keyword( $target_post );

		// Ottimizza slug se abilitato.
		$this->optimize_slug( $target_post );

		// Genera OG tags.
		$this->generate_og_tags( $target_post );

		$this->logger->log(
			'debug',
			sprintf( 'SEO ottimizzato per post #%d', $target_post->ID ),
			array( 'post_id' => $target_post->ID )
		);
	}

	/**
	 * Genera meta description automaticamente.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post tradotto.
	 *
	 * @return void
	 */
	protected function generate_meta_description( $post ) {
		// Controlla se esiste già (Yoast SEO, Rank Math, etc.).
		$existing = $this->get_existing_meta_description( $post );

		if ( $existing && strlen( $existing ) > 50 ) {
			return; // Già presente, non sovrascrivere.
		}

		// Genera description ottimizzata (max 160 caratteri).
		$content     = wp_strip_all_tags( $post->post_content );
		$content     = preg_replace( '/\s+/', ' ', $content );
		$content     = trim( $content );
		$description = wp_trim_words( $content, 25, '...' );

		// Limita a 160 caratteri.
		if ( strlen( $description ) > 160 ) {
			$description = substr( $description, 0, 157 ) . '...';
		}

		// Salva usando i meta key più comuni.
		$this->save_meta_description( $post, $description );
	}

	/**
	 * Ottiene meta description esistente.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string
	 */
	protected function get_existing_meta_description( $post ) {
		// Yoast SEO.
		$yoast = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( $yoast ) {
			return $yoast;
		}

		// Rank Math.
		$rank_math = get_post_meta( $post->ID, 'rank_math_description', true );
		if ( $rank_math ) {
			return $rank_math;
		}

		// All in One SEO.
		$aioseo = get_post_meta( $post->ID, '_aioseo_description', true );
		if ( $aioseo ) {
			return $aioseo;
		}

		// SEOPress.
		$seopress = get_post_meta( $post->ID, '_seopress_titles_desc', true );
		if ( $seopress ) {
			return $seopress;
		}

		return '';
	}

	/**
	 * Salva meta description.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post        Post object.
	 * @param string  $description Description.
	 *
	 * @return void
	 */
	protected function save_meta_description( $post, $description ) {
		// Rileva quale plugin SEO è attivo.
		if ( defined( 'WPSEO_VERSION' ) ) {
			// Yoast SEO.
			update_post_meta( $post->ID, '_yoast_wpseo_metadesc', $description );
		} elseif ( class_exists( 'RankMath' ) ) {
			// Rank Math.
			update_post_meta( $post->ID, 'rank_math_description', $description );
		} elseif ( defined( 'AIOSEO_VERSION' ) ) {
			// All in One SEO.
			update_post_meta( $post->ID, '_aioseo_description', $description );
		} elseif ( defined( 'SEOPRESS_VERSION' ) ) {
			// SEOPress.
			update_post_meta( $post->ID, '_seopress_titles_desc', $description );
		}

		// Salva anche in un meta generico per compatibilità.
		update_post_meta( $post->ID, '_fpml_meta_description', $description );
	}

	/**
	 * Genera focus keyword basata sul titolo.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post tradotto.
	 *
	 * @return void
	 */
	protected function generate_focus_keyword( $post ) {
		$existing = $this->get_existing_focus_keyword( $post );

		if ( $existing ) {
			return; // Già presente.
		}

		// Estrai 2-3 parole più significative dal titolo.
		$title = $post->post_title;
		$title = strtolower( $title );

		// Rimuovi stop words comuni in inglese.
		$stop_words = array( 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'are', 'was', 'were', 'be', 'been', 'being' );
		$words      = explode( ' ', $title );
		$words      = array_diff( $words, $stop_words );
		$words      = array_values( $words );

		if ( empty( $words ) ) {
			return;
		}

		// Prendi le prime 2-3 parole significative.
		$keyword = implode( ' ', array_slice( $words, 0, min( 3, count( $words ) ) ) );

		$this->save_focus_keyword( $post, $keyword );
	}

	/**
	 * Ottiene focus keyword esistente.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return string
	 */
	protected function get_existing_focus_keyword( $post ) {
		// Yoast SEO.
		$yoast = get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );
		if ( $yoast ) {
			return $yoast;
		}

		// Rank Math.
		$rank_math = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
		if ( $rank_math ) {
			return $rank_math;
		}

		return '';
	}

	/**
	 * Salva focus keyword.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post    Post object.
	 * @param string  $keyword Keyword.
	 *
	 * @return void
	 */
	protected function save_focus_keyword( $post, $keyword ) {
		if ( defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $post->ID, '_yoast_wpseo_focuskw', $keyword );
		} elseif ( class_exists( 'RankMath' ) ) {
			update_post_meta( $post->ID, 'rank_math_focus_keyword', $keyword );
		}

		update_post_meta( $post->ID, '_fpml_focus_keyword', $keyword );
	}

	/**
	 * Ottimizza lo slug del post.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post tradotto.
	 *
	 * @return void
	 */
	protected function optimize_slug( $post ) {
		if ( ! $this->settings || ! $this->settings->get( 'translate_slugs', false ) ) {
			return;
		}

		// Lo slug è già gestito da FPML_SEO, qui possiamo solo validare.
		$slug = $post->post_name;

		if ( ! $slug || strlen( $slug ) < 3 ) {
			// Genera slug dal titolo.
			$new_slug = sanitize_title( $post->post_title );

			if ( $new_slug && $new_slug !== $slug ) {
				wp_update_post(
					array(
						'ID'        => $post->ID,
						'post_name' => $new_slug,
					)
				);
			}
		}
	}

	/**
	 * Genera Open Graph tags.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post tradotto.
	 *
	 * @return void
	 */
	protected function generate_og_tags( $post ) {
		// OG Title.
		if ( ! get_post_meta( $post->ID, '_yoast_wpseo_opengraph-title', true ) ) {
			update_post_meta( $post->ID, '_yoast_wpseo_opengraph-title', $post->post_title );
		}

		// OG Description.
		$description = $this->get_existing_meta_description( $post );
		if ( $description && ! get_post_meta( $post->ID, '_yoast_wpseo_opengraph-description', true ) ) {
			update_post_meta( $post->ID, '_yoast_wpseo_opengraph-description', $description );
		}

		// OG Image (usa featured image se presente).
		if ( has_post_thumbnail( $post->ID ) && ! get_post_meta( $post->ID, '_yoast_wpseo_opengraph-image', true ) ) {
			$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'large' );
			if ( $thumbnail_url ) {
				update_post_meta( $post->ID, '_yoast_wpseo_opengraph-image', $thumbnail_url );
			}
		}
	}

	/**
	 * Aggiunge meta box per preview SEO.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function add_seo_preview_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fpml_seo_preview',
				__( 'SEO Preview (EN)', 'fp-multilanguage' ),
				array( $this, 'render_seo_preview_meta_box' ),
				$post_type,
				'normal',
				'low'
			);
		}
	}

	/**
	 * Renderizza meta box SEO preview.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_seo_preview_meta_box( $post ) {
		// Mostra solo per post tradotti.
		if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			echo '<p>' . esc_html__( 'Questo è il post originale. La preview SEO è disponibile sul post tradotto.', 'fp-multilanguage' ) . '</p>';
			return;
		}

		$title       = $post->post_title;
		$description = $this->get_existing_meta_description( $post );
		$keyword     = $this->get_existing_focus_keyword( $post );
		$permalink   = get_permalink( $post->ID );

		?>
		<div class="fpml-seo-preview" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 4px;">
			<p style="margin: 0 0 10px;"><strong><?php esc_html_e( 'Anteprima Google:', 'fp-multilanguage' ); ?></strong></p>
			
			<div style="max-width: 600px;">
				<div style="color: #1a0dab; font-size: 18px; line-height: 1.3; margin-bottom: 5px;">
					<?php echo esc_html( $title ); ?>
				</div>
				<div style="color: #006621; font-size: 14px; margin-bottom: 5px;">
					<?php echo esc_html( $permalink ); ?>
				</div>
				<div style="color: #545454; font-size: 13px; line-height: 1.4;">
					<?php echo esc_html( $description ? $description : __( 'Nessuna description impostata.', 'fp-multilanguage' ) ); ?>
				</div>
			</div>

			<?php if ( $keyword ) : ?>
				<p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
					<strong><?php esc_html_e( 'Focus Keyword:', 'fp-multilanguage' ); ?></strong>
					<code><?php echo esc_html( $keyword ); ?></code>
				</p>
			<?php endif; ?>

			<p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
				<small style="color: #666;">
					<?php
					esc_html_e( 'Questo è un\'anteprima di come apparirà il contenuto nei risultati di ricerca Google. I meta SEO sono stati ottimizzati automaticamente.', 'fp-multilanguage' );
					?>
				</small>
			</p>
		</div>
		<?php
	}

	/**
	 * Analizza la leggibilità del contenuto (Flesch Reading Ease).
	 *
	 * @since 0.4.0
	 *
	 * @param string $text Testo da analizzare.
	 *
	 * @return array Score e label.
	 */
	public function analyze_readability( $text ) {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( empty( $text ) ) {
			return array(
				'score' => 0,
				'label' => 'N/A',
			);
		}

		// Conta parole, frasi e sillabe (approssimazione).
		$words     = str_word_count( $text );
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = count( $sentences );

		// Stima sillabe (molto approssimativa per l'inglese).
		preg_match_all( '/[aeiou]+/i', $text, $matches );
		$syllables = count( $matches[0] );

		if ( 0 === $words || 0 === $sentences ) {
			return array(
				'score' => 0,
				'label' => 'N/A',
			);
		}

		// Formula Flesch Reading Ease.
		$score = 206.835 - 1.015 * ( $words / $sentences ) - 84.6 * ( $syllables / $words );
		$score = round( $score, 1 );

		// Classificazione.
		if ( $score >= 90 ) {
			$label = __( 'Molto facile', 'fp-multilanguage' );
		} elseif ( $score >= 80 ) {
			$label = __( 'Facile', 'fp-multilanguage' );
		} elseif ( $score >= 70 ) {
			$label = __( 'Abbastanza facile', 'fp-multilanguage' );
		} elseif ( $score >= 60 ) {
			$label = __( 'Medio', 'fp-multilanguage' );
		} elseif ( $score >= 50 ) {
			$label = __( 'Abbastanza difficile', 'fp-multilanguage' );
		} elseif ( $score >= 30 ) {
			$label = __( 'Difficile', 'fp-multilanguage' );
		} else {
			$label = __( 'Molto difficile', 'fp-multilanguage' );
		}

		return array(
			'score' => $score,
			'label' => $label,
		);
	}
}
