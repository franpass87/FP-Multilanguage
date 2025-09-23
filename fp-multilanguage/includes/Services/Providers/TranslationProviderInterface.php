<?php
namespace FPMultilanguage\Services\Providers;

use FPMultilanguage\Services\TranslationResponse;

interface TranslationProviderInterface {

	public function get_name(): string;

	/**
	 * @param array<string, mixed> $options
	 */
	public function translate( string $text, string $source, string $target, array $options = array() ): ?TranslationResponse;
}
