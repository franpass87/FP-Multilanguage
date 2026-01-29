<?php
/**
 * Translation Job Domain Model.
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
 * Translation job domain model.
 *
 * Represents a single translation job in the queue.
 *
 * @since 1.0.0
 */
class TranslationJob {
	/**
	 * Job ID.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Source object ID.
	 *
	 * @var int
	 */
	protected $object_id;

	/**
	 * Object type (post, term, comment).
	 *
	 * @var string
	 */
	protected $object_type;

	/**
	 * Field to translate.
	 *
	 * @var string
	 */
	protected $field;

	/**
	 * Job state (pending, translating, completed, failed).
	 *
	 * @var string
	 */
	protected $state;

	/**
	 * Target language code.
	 *
	 * @var string
	 */
	protected $target_language;

	/**
	 * Retry count.
	 *
	 * @var int
	 */
	protected $retry_count;

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	protected $error_message;

	/**
	 * Created timestamp.
	 *
	 * @var int
	 */
	protected $created_at;

	/**
	 * Updated timestamp.
	 *
	 * @var int
	 */
	protected $updated_at;

	/**
	 * Constructor.
	 *
	 * @param int         $id             Job ID.
	 * @param int         $object_id      Source object ID.
	 * @param string      $object_type    Object type.
	 * @param string      $field          Field to translate.
	 * @param string      $state          Job state.
	 * @param string      $target_language Target language code.
	 * @param int         $retry_count    Retry count.
	 * @param string|null $error_message  Error message.
	 * @param int         $created_at     Created timestamp.
	 * @param int         $updated_at     Updated timestamp.
	 */
	public function __construct(
		int $id,
		int $object_id,
		string $object_type,
		string $field,
		string $state,
		string $target_language,
		int $retry_count = 0,
		?string $error_message = null,
		int $created_at = 0,
		int $updated_at = 0
	) {
		$this->id              = $id;
		$this->object_id       = $object_id;
		$this->object_type    = $object_type;
		$this->field          = $field;
		$this->state          = $state;
		$this->target_language = $target_language;
		$this->retry_count    = $retry_count;
		$this->error_message  = $error_message;
		$this->created_at     = $created_at ?: time();
		$this->updated_at     = $updated_at ?: time();
	}

	/**
	 * Get job ID.
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Get object ID.
	 *
	 * @return int
	 */
	public function getObjectId(): int {
		return $this->object_id;
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
	 * Get field.
	 *
	 * @return string
	 */
	public function getField(): string {
		return $this->field;
	}

	/**
	 * Get state.
	 *
	 * @return string
	 */
	public function getState(): string {
		return $this->state;
	}

	/**
	 * Set state.
	 *
	 * @param string $state State.
	 * @return void
	 */
	public function setState( string $state ): void {
		$this->state      = $state;
		$this->updated_at = time();
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
	 * Get retry count.
	 *
	 * @return int
	 */
	public function getRetryCount(): int {
		return $this->retry_count;
	}

	/**
	 * Increment retry count.
	 *
	 * @return void
	 */
	public function incrementRetryCount(): void {
		$this->retry_count++;
		$this->updated_at = time();
	}

	/**
	 * Get error message.
	 *
	 * @return string|null
	 */
	public function getErrorMessage(): ?string {
		return $this->error_message;
	}

	/**
	 * Set error message.
	 *
	 * @param string|null $error_message Error message.
	 * @return void
	 */
	public function setErrorMessage( ?string $error_message ): void {
		$this->error_message = $error_message;
		$this->updated_at     = time();
	}

	/**
	 * Get created timestamp.
	 *
	 * @return int
	 */
	public function getCreatedAt(): int {
		return $this->created_at;
	}

	/**
	 * Get updated timestamp.
	 *
	 * @return int
	 */
	public function getUpdatedAt(): int {
		return $this->updated_at;
	}
}














