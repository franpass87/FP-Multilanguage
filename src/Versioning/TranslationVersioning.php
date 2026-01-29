<?php
/**
 * Translation Versioning wrapper - Aliased from Core\TranslationVersioning.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 * @deprecated 1.0.0 Use FP\Multilanguage\Core\TranslationVersioning instead.
 */

namespace FP\Multilanguage\Versioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TranslationVersioning wrapper class.
 *
 * This class extends Core\TranslationVersioning to maintain backward compatibility
 * with code that references the old FP\Multilanguage\Versioning namespace.
 *
 * @since 0.10.0
 * @deprecated 1.0.0 Use FP\Multilanguage\Core\TranslationVersioning instead.
 */
class TranslationVersioning extends \FP\Multilanguage\Core\TranslationVersioning {
	// This class inherits all methods from Core\TranslationVersioning
	// It exists only for backward compatibility
}
