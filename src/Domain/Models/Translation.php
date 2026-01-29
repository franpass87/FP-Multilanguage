<?php
/**
 * Translation Domain Model.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Domain\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translation domain model.
 *
 * Represents a translation relationship between source and target content.
 *
 * @since 1.0.0
 */
class Translation {
	/**
	 * Source object ID.
	 *
	 * @var int
	 */
	protected $source_id;

	/**
	 * Target object ID.
	 *
	 * @var int
	 */
	protected $target_id;

	/**
	 * Object type (post, term, comment).
	 *
	 * @var string
	 */
	protected $object_type;

	/**
	 * Target language code.
	 *
	 * @var string
	 */
	protected $target_language;

	/**
	 * Translation status.
	 *
	 * @var string
	 */
	protected $status;

	/**
	 * Constructor.
	 *
	 * @param int    $source_id      Source object ID.
	 * @param int    $target_id      Target object ID.
	 * @param string $object_type    Object type.
	 * @param string $target_language Target language code.
	 * @param string $status         Translation status.
	 */
	public function __construct( int $source_id, int $target_id, string $object_type, string $target_language, string $status = 'pending' ) {
		$this->source_id      = $source_id;
		$this->target_id      = $target_id;
		$this->object_type    = $object_type;
		$this->target_language = $target_language;
		$this->status         = $status;
	}

	/**
	 * Get source ID.
	 *
	 * @return int
	 */
	public function getSourceId(): int {
		return $this->source_id;
	}

	/**
	 * Get target ID.
	 *
	 * @return int
	 */
	public function getTargetId(): int {
		return $this->target_id;
	}

	/**
	 * Get object type.
	 *
	 * @return string
	 */
	public function getObjectType(): string {
		return $this->object_type;
	}

	/**
	 * Get target language.
	 *
	 * @return string
	 */
	public function getTargetLanguage(): string {
		return $this->target_language;
	}

	/**
	 * Get status.
	 *
	 * @return string
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * Set status.
	 *
	 * @param string $status Status.
	 * @return void
	 */
	public function setStatus( string $status ): void {
		$this->status = $status;
	}
}














