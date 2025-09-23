<?php
namespace FPMultilanguage\Services;

class TranslationResponse {

	private string $text;

	private bool $cacheable;

	/**
	 * @var array<string, mixed>
	 */
	private array $meta;

	public function __construct( string $text, bool $cacheable = true, array $meta = array() ) {
		$this->text      = $text;
		$this->cacheable = $cacheable;
		$this->meta      = $meta;
	}

	public function get_text(): string {
		return $this->text;
	}

	public function is_cacheable(): bool {
		return $this->cacheable;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_meta(): array {
		return $this->meta;
	}
}
