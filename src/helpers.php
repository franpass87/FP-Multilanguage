<?php
/**
 * Helper functions for FP Multilanguage
 * These functions are in the global namespace for easy access.
 *
 * @package FP_Multilanguage
 * @since 0.9.3
 * @deprecated 1.0.0 These functions are kept for backward compatibility.
 *              New code should use domain services and repository pattern instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safely update a post without triggering problematic hooks.
 * Can be called from any class.
 *
 * @since 0.9.3
 *
 * @param array $post_data Post data array for wp_update_post.
 * @return int|\WP_Error Post ID on success, WP_Error on failure.
 */
function fpml_safe_update_post( $post_data ) {
	// Se già in modalità safe, usa direttamente wp_update_post
	if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
		return wp_update_post( $post_data, true );
	}
	
	global $wp_filter;
	
	// Save current hooks
	$saved_hooks = array();
	$problematic_hooks = array( 'save_post', 'publish_post', 'transition_post_status', 'publish_page', 'on_publish' );
	
	// Remove problematic hooks temporarily
	foreach ( $problematic_hooks as $hook_name ) {
		if ( isset( $wp_filter[ $hook_name ] ) ) {
			$saved_hooks[ $hook_name ] = $wp_filter[ $hook_name ];
			unset( $wp_filter[ $hook_name ] );
		}
	}
	
	// Set flag to prevent recursion
	$GLOBALS['fpml_updating_translation'] = true;
	
	try {
		// Update post
		$result = wp_update_post( $post_data, true );
	} finally {
		// Always restore hooks, even if there's an error
		foreach ( $saved_hooks as $hook_name => $hook_data ) {
			$wp_filter[ $hook_name ] = $hook_data;
		}
		
		// Clear flag
		unset( $GLOBALS['fpml_updating_translation'] );
	}
	
	return $result;
}

/**
 * Safely insert a post without triggering problematic hooks.
 * Can be called from any class.
 *
 * @since 0.9.3
 *
 * @param array $post_data Post data array for wp_insert_post.
 * @return int|\WP_Error Post ID on success, WP_Error on failure.
 */
function fpml_safe_insert_post( $post_data ) {
	// Se già in modalità safe, usa direttamente wp_insert_post
	if ( isset( $GLOBALS['fpml_updating_translation'] ) && $GLOBALS['fpml_updating_translation'] ) {
		return wp_insert_post( $post_data, true );
	}
	
	global $wp_filter;
	
	// Save current hooks
	$saved_hooks = array();
	$problematic_hooks = array( 'save_post', 'publish_post', 'transition_post_status', 'publish_page', 'on_publish' );
	
	// Remove problematic hooks temporarily
	foreach ( $problematic_hooks as $hook_name ) {
		if ( isset( $wp_filter[ $hook_name ] ) ) {
			$saved_hooks[ $hook_name ] = $wp_filter[ $hook_name ];
			unset( $wp_filter[ $hook_name ] );
		}
	}
	
	// Set flag to prevent recursion
	$GLOBALS['fpml_updating_translation'] = true;
	
	try {
		// Insert post
		$result = wp_insert_post( $post_data, true );
	} finally {
		// Always restore hooks, even if there's an error
		foreach ( $saved_hooks as $hook_name => $hook_data ) {
			$wp_filter[ $hook_name ] = $hook_data;
		}
		
		// Clear flag
		unset( $GLOBALS['fpml_updating_translation'] );
	}
	
	return $result;
}

/**
 * Safely insert a term without triggering problematic hooks.
 * Can be called from any class.
 *
 * @since 0.9.3
 *
 * @param string       $term     The term name to add.
 * @param string       $taxonomy Taxonomy slug.
 * @param array|string $args     Optional. Array or query string of arguments.
 * @return array|\WP_Error Term ID and term taxonomy ID on success, WP_Error on failure.
 */
function fpml_safe_insert_term( $term, $taxonomy, $args = array() ) {
	// Se già in modalità safe, usa direttamente wp_insert_term
	if ( isset( $GLOBALS['fpml_creating_term_translation'] ) && $GLOBALS['fpml_creating_term_translation'] ) {
		return wp_insert_term( $term, $taxonomy, $args );
	}
	
	global $wp_filter;
	
	// Save current hooks
	$saved_hooks = array();
	$problematic_hooks = array( 'created_term', 'edited_term' );
	
	// Remove problematic hooks temporarily
	foreach ( $problematic_hooks as $hook_name ) {
		if ( isset( $wp_filter[ $hook_name ] ) ) {
			$saved_hooks[ $hook_name ] = $wp_filter[ $hook_name ];
			unset( $wp_filter[ $hook_name ] );
		}
	}
	
	// Set flag to prevent recursion
	$GLOBALS['fpml_creating_term_translation'] = true;
	
	try {
		// Insert term
		$result = wp_insert_term( $term, $taxonomy, $args );
	} finally {
		// Always restore hooks, even if there's an error
		foreach ( $saved_hooks as $hook_name => $hook_data ) {
			$wp_filter[ $hook_name ] = $hook_data;
		}
		
		// Clear flag
		unset( $GLOBALS['fpml_creating_term_translation'] );
	}
	
	return $result;
}

/**
 * Safely update a term without triggering problematic hooks.
 * Can be called from any class.
 *
 * @since 0.9.3
 *
 * @param int          $term_id  Term ID.
 * @param string       $taxonomy Taxonomy slug.
 * @param array|string $args     Optional. Array or query string of arguments.
 * @return array|\WP_Error Term ID and term taxonomy ID on success, WP_Error on failure.
 */
function fpml_safe_update_term( $term_id, $taxonomy, $args = array() ) {
	// Se già in modalità safe, usa direttamente wp_update_term
	if ( isset( $GLOBALS['fpml_updating_term_translation'] ) && $GLOBALS['fpml_updating_term_translation'] ) {
		return wp_update_term( $term_id, $taxonomy, $args );
	}
	
	global $wp_filter;
	
	// Save current hooks
	$saved_hooks = array();
	$problematic_hooks = array( 'created_term', 'edited_term' );
	
	// Remove problematic hooks temporarily
	foreach ( $problematic_hooks as $hook_name ) {
		if ( isset( $wp_filter[ $hook_name ] ) ) {
			$saved_hooks[ $hook_name ] = $wp_filter[ $hook_name ];
			unset( $wp_filter[ $hook_name ] );
		}
	}
	
	// Set flag to prevent recursion
	$GLOBALS['fpml_updating_term_translation'] = true;
	
	try {
		// Update term
		$result = wp_update_term( $term_id, $taxonomy, $args );
	} finally {
		// Always restore hooks, even if there's an error
		foreach ( $saved_hooks as $hook_name => $hook_data ) {
			$wp_filter[ $hook_name ] = $hook_data;
		}
		
		// Clear flag
		unset( $GLOBALS['fpml_updating_term_translation'] );
	}
	
	return $result;
}

/**
 * Get current language code.
 *
 * Helper function for other plugins to easily check the current language.
 *
 * @since 0.9.6
 *
 * @return string 'it' or 'en'
 */
function fpml_get_current_language() {
	if ( ! class_exists( '\FP\Multilanguage\Language' ) ) {
		return 'it';
	}
	return \FP\Multilanguage\Language::instance()->get_current_language();
}

/**
 * Check if current language is English.
 *
 * Helper function for other plugins to easily check if user is on /en/.
 *
 * @since 0.9.6
 *
 * @return bool True if English, false if Italian.
 */
function fpml_is_english() {
	return ( 'en' === fpml_get_current_language() );
}

/**
 * Check if current language is Italian.
 *
 * Helper function for other plugins to easily check if user is on Italian version.
 *
 * @since 0.9.6
 *
 * @return bool True if Italian, false if English.
 */
function fpml_is_italian() {
	return ( 'it' === fpml_get_current_language() );
}

/**
 * Get translation ID for a specific language.
 *
 * @since 0.10.0
 *
 * @param int    $post_id Source post ID.
 * @param string $target_lang Target language code. Default 'en'.
 * @return int|false Translation post ID, or false if not found.
 */
function fpml_get_translation_id( $post_id, $target_lang = 'en' ) {
	$manager = fpml_get_translation_manager();
	if ( ! $manager ) {
		return false;
	}
	return $manager->get_translation_id( $post_id, $target_lang );
}

/**
 * Get all translations for a post.
 *
 * @since 0.10.0
 *
 * @param int $post_id Source post ID.
 * @return array Array of [language_code => translation_id] pairs.
 */
function fpml_get_all_translations( $post_id ) {
	$manager = fpml_get_translation_manager();
	if ( ! $manager ) {
		return array();
	}
	return $manager->get_all_translations( $post_id );
}

/**
 * Get enabled languages.
 *
 * @since 0.10.0
 *
 * @return array Array of enabled language codes.
 */
function fpml_get_enabled_languages() {
	$manager = fpml_get_language_manager();
	if ( ! $manager ) {
		return array( 'en' );
	}
	return $manager->get_enabled_languages();
}

/**
 * Check if a language is a target language (enabled for translation).
 *
 * @since 0.10.0
 *
 * @param string $lang Language code to check.
 * @return bool True if the language is enabled as a target language.
 */
function fpml_is_target_language( $lang ) {
	$enabled = fpml_get_enabled_languages();
	return in_array( $lang, $enabled, true );
}

/**
 * Check if a URL contains a target language path.
 *
 * @since 0.10.0
 *
 * @param string $url URL to check.
 * @return bool True if the URL contains a target language path.
 */
function fpml_url_contains_target_language( $url ) {
	if ( empty( $url ) ) {
		return false;
	}
	
	$language_manager = fpml_get_language_manager();
	if ( ! $language_manager ) {
		// Fallback: check for /en/ if LanguageManager not available
		return false !== strpos( $url, '/en/' );
	}
	
	$enabled_languages = $language_manager->get_enabled_languages();
	foreach ( $enabled_languages as $lang_code ) {
		$lang_info = $language_manager->get_language_info( $lang_code );
		if ( $lang_info && ! empty( $lang_info['slug'] ) ) {
			$lang_slug = trim( $lang_info['slug'], '/' );
			if ( ! empty( $lang_slug ) && false !== strpos( $url, '/' . $lang_slug . '/' ) ) {
				return true;
			}
		}
	}
	
	return false;
}

/**
 * Get the language slug for the current language.
 *
 * @since 0.10.0
 *
 * @param string|null $lang_code Optional language code. If not provided, uses current language.
 * @return string Language slug (e.g., 'en', 'de', 'fr', 'es').
 */
function fpml_get_language_slug( $lang_code = null ) {
	if ( empty( $lang_code ) ) {
		$lang_code = fpml_get_current_language();
	}
	
	$language_manager = fpml_get_language_manager();
	if ( ! $language_manager ) {
		return 'en'; // Fallback
	}
	
	$lang_info = $language_manager->get_language_info( $lang_code );
	if ( $lang_info && ! empty( $lang_info['slug'] ) ) {
		return trim( $lang_info['slug'], '/' );
	}
	
	return 'en'; // Fallback
}


/**
 * Get Logger service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Logger|null Logger instance or null if not available.
 */
function fpml_get_logger() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'logger' ) ) {
		try {
			return $container->get( 'logger' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Logger' ) ) {
		return \FP\Multilanguage\Logger::instance();
	}
	return null;
}

/**
 * Get Settings service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Settings|null Settings instance or null if not available.
 */
function fpml_get_settings() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'options' ) ) {
		try {
			return $container->get( 'options' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Settings' ) ) {
		return \FP\Multilanguage\Settings::instance();
	}
	return null;
}

/**
 * Get LanguageManager service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\MultiLanguage\LanguageManager|null LanguageManager instance or null if not available.
 */
function fpml_get_language_manager() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'language.manager' ) ) {
		try {
			return $container->get( 'language.manager' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\MultiLanguage\LanguageManager' ) ) {
		return \FP\Multilanguage\MultiLanguage\LanguageManager::instance();
	}
	return null;
}

/**
 * Get Processor service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Processor|null Processor instance or null if not available.
 */
function fpml_get_processor() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'processor' ) ) {
		try {
			return $container->get( 'processor' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Processor' ) ) {
		return \FP\Multilanguage\Processor::instance();
	}
	return null;
}

/**
 * Get ContentIndexer service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Content\ContentIndexer|null ContentIndexer instance or null if not available.
 */
function fpml_get_content_indexer() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'content.indexer' ) ) {
		try {
			return $container->get( 'content.indexer' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Content\ContentIndexer' ) ) {
		return \FP\Multilanguage\Content\ContentIndexer::instance();
	}
	return null;
}

/**
 * Get Diagnostics service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Diagnostics\Diagnostics|null Diagnostics instance or null if not available.
 */
function fpml_get_diagnostics() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'diagnostics' ) ) {
		try {
			return $container->get( 'diagnostics' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Diagnostics\Diagnostics' ) ) {
		return \FP\Multilanguage\Diagnostics\Diagnostics::instance();
	}
	return null;
}

/**
 * Get JobEnqueuer service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Translation\JobEnqueuer|null JobEnqueuer instance or null if not available.
 */
function fpml_get_job_enqueuer() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'translation.job_enqueuer' ) ) {
		try {
			return $container->get( 'translation.job_enqueuer' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Translation\JobEnqueuer' ) ) {
		return \FP\Multilanguage\Translation\JobEnqueuer::instance();
	}
	return null;
}

/**
 * Get CostEstimator service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Diagnostics\CostEstimator|null CostEstimator instance or null if not available.
 */
function fpml_get_cost_estimator() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'cost_estimator' ) ) {
		try {
			return $container->get( 'cost_estimator' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Diagnostics\CostEstimator' ) ) {
		return \FP\Multilanguage\Diagnostics\CostEstimator::instance();
	}
	return null;
}

/**
 * Get Glossary service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Glossary|null Glossary instance or null if not available.
 */
function fpml_get_glossary() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'glossary' ) ) {
		try {
			return $container->get( 'glossary' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Glossary' ) ) {
		return \FP\Multilanguage\Glossary::instance();
	}
	return null;
}

/**
 * Get HookManager service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Core\HookManager|null HookManager instance or null if not available.
 */
function fpml_get_hook_manager() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'hook.manager' ) ) {
		try {
			return $container->get( 'hook.manager' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Core\HookManager' ) ) {
		return \FP\Multilanguage\Core\HookManager::instance();
	}
	return null;
}

/**
 * Get TranslationVersioning service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Core\TranslationVersioning|null TranslationVersioning instance or null if not available.
 */
function fpml_get_translation_versioning() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'translation.versioning' ) ) {
		try {
			return $container->get( 'translation.versioning' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Core\TranslationVersioning' ) ) {
		return \FP\Multilanguage\Core\TranslationVersioning::instance();
	}
	return null;
}

/**
 * Get the Kernel container instance.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Kernel\Container|null Container instance or null if not available.
 */
function fpml_get_container() {
	if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
		$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
		if ( $kernel ) {
			return $kernel->getContainer();
		}
	}
	return null;
}

/**
 * Get Queue service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Queue|null Queue instance or null if not available.
 */
function fpml_get_queue() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'queue' ) ) {
		try {
			return $container->get( 'queue' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Queue' ) ) {
		return \FP\Multilanguage\Queue::instance();
	}
	return null;
}

/**
 * Get TranslationManager service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Content\TranslationManager|null TranslationManager instance or null if not available.
 */
function fpml_get_translation_manager() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'translation.manager' ) ) {
		try {
			return $container->get( 'translation.manager' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Content\TranslationManager' ) ) {
		return \FP\Multilanguage\Content\TranslationManager::instance();
	}
	return null;
}

/**
 * Get Options/Settings service from container (FPML_Settings).
 *
 * @since 1.0.0
 *
 * @return \FPML_Settings|object|null Options instance or null if not available.
 */
function fpml_get_options() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'options' ) ) {
		try {
			return $container->get( 'options' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FPML_Settings' ) ) {
		return \FPML_Settings::instance();
	}
	return null;
}

/**
 * Get Plugin service from container (FPML_Plugin).
 *
 * @since 1.0.0
 *
 * @return \FPML_Plugin|object|null Plugin instance or null if not available.
 */
function fpml_get_plugin() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'plugin' ) ) {
		try {
			return $container->get( 'plugin' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FPML_Plugin' ) ) {
		return \FPML_Plugin::instance();
	}
	return null;
}

/**
 * Get PostHandlers service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Core\PostHandlers|\FP\Multilanguage\Admin\PostHandlers|null PostHandlers instance or null.
 */
function fpml_get_post_handlers() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'post.handlers' ) ) {
		try {
			return $container->get( 'post.handlers' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Core\PostHandlers' ) ) {
		return \FP\Multilanguage\Core\PostHandlers::instance();
	}
	if ( class_exists( '\FP\Multilanguage\Admin\PostHandlers' ) ) {
		return \FP\Multilanguage\Admin\PostHandlers::instance();
	}
	return null;
}

/**
 * Get ContentHandlers service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Core\ContentHandlers|null ContentHandlers instance or null if not available.
 */
function fpml_get_content_handlers() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'content.handlers' ) ) {
		try {
			return $container->get( 'content.handlers' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Core\ContentHandlers' ) ) {
		return \FP\Multilanguage\Core\ContentHandlers::instance();
	}
	return null;
}

/**
 * Get TermHandlers service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Core\TermHandlers|null TermHandlers instance or null if not available.
 */
function fpml_get_term_handlers() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'term.handlers' ) ) {
		try {
			return $container->get( 'term.handlers' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Core\TermHandlers' ) ) {
		return \FP\Multilanguage\Core\TermHandlers::instance();
	}
	return null;
}

/**
 * Get Rewrites service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Rewrites|object|null Rewrites instance or null if not available.
 */
function fpml_get_rewrites() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'rewrites' ) ) {
		try {
			return $container->get( 'rewrites' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Rewrites' ) ) {
		return \FP\Multilanguage\Rewrites::instance();
	}
	if ( class_exists( '\FPML_Rewrites' ) ) {
		return \FPML_Rewrites::instance();
	}
	return null;
}

/**
 * Get Language service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\Language|object|null Language instance or null if not available.
 */
function fpml_get_language() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'language' ) ) {
		try {
			return $container->get( 'language' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\Language' ) ) {
		return \FP\Multilanguage\Language::instance();
	}
	if ( class_exists( '\FPML_Language' ) ) {
		return \FPML_Language::instance();
	}
	return null;
}

/**
 * Get MenuSync service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\MenuSync|object|null MenuSync instance or null if not available.
 */
function fpml_get_menu_sync() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'menu_sync' ) ) {
		try {
			return $container->get( 'menu_sync' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\MenuSync' ) ) {
		return \FP\Multilanguage\MenuSync::instance();
	}
	if ( class_exists( '\FPML_Menu_Sync' ) ) {
		return \FPML_Menu_Sync::instance();
	}
	return null;
}

/**
 * Get ContentDiff service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\ContentDiff|object|null ContentDiff instance or null if not available.
 */
function fpml_get_content_diff() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'content_diff' ) ) {
		try {
			return $container->get( 'content_diff' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\ContentDiff' ) ) {
		return \FP\Multilanguage\ContentDiff::instance();
	}
	if ( class_exists( '\FPML_Content_Diff' ) ) {
		return \FPML_Content_Diff::instance();
	}
	return null;
}

/**
 * Get ExportImport service from container.
 *
 * @since 1.0.0
 *
 * @return \FP\Multilanguage\ExportImport|object|null ExportImport instance or null if not available.
 */
function fpml_get_export_import() {
	$container = fpml_get_container();
	if ( $container && $container->has( 'export_import' ) ) {
		try {
			return $container->get( 'export_import' );
		} catch ( \Exception $e ) {
			// Fallback to singleton
		}
	}
	if ( class_exists( '\FP\Multilanguage\ExportImport' ) ) {
		return \FP\Multilanguage\ExportImport::instance();
	}
	if ( class_exists( '\FPML_Export_Import' ) ) {
		return \FPML_Export_Import::instance();
	}
	return null;
}

/**
 * Alias for backward compatibility.
 * Use fpml_get_processor() instead.
 *
 * @since 1.0.0
 * @deprecated Use fpml_get_processor() instead.
 *
 * @return \FP\Multilanguage\Processor|null Processor instance or null if not available.
 */
if ( ! function_exists( 'FPML_fpml_get_processor' ) ) {
	function FPML_fpml_get_processor() {
		return fpml_get_processor();
	}
}
