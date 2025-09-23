<?php
namespace FPMultilanguage\Services;

use FPMultilanguage\Admin\Settings;

class TranslationService
{
    public const CACHE_GROUP = 'fp_multilanguage_translations';

    private const QUOTA_TRANSIENT_PREFIX = 'fp_multilanguage_quota_';

    private const CACHE_VERSION_OPTION = 'fp_multilanguage_cache_version';

    private static array $runtimeCache = [];

    public function translate(string $text, string $source, string $target, array $args = []): string
    {
        $text = trim($text);
        if ($text === '' || $source === $target) {
            return $text;
        }

        $cacheKey = $this->get_cache_key($text, $source, $target, $args);
        $cached = $this->get_cache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $manual = $this->lookup_manual_translation($text, $target);
        if ($manual !== null) {
            $this->set_cache($cacheKey, $manual);

            return $manual;
        }

        $providers = Settings::get_enabled_providers();
        $result = null;

        foreach ($providers as $provider) {
            if ($this->is_quota_exceeded($provider)) {
                continue;
            }

            $translated = $this->dispatch_translation($provider, $text, $source, $target, $args);
            if ($translated !== null && $translated !== '') {
                $this->increment_quota($provider, mb_strlen($text));
                $result = $translated;
                break;
            }
        }

        if ($result === null) {
            $result = $this->manual_fallback($text, $target, $args);
        }

        $this->set_cache($cacheKey, $result);

        return $result;
    }

    public static function flush_cache(): void
    {
        if (function_exists('update_option') && function_exists('get_option')) {
            $version = (int) get_option(self::CACHE_VERSION_OPTION, 1);
            update_option(self::CACHE_VERSION_OPTION, $version + 1);
        }

        self::$runtimeCache = [];
    }

    private function dispatch_translation(string $provider, string $text, string $source, string $target, array $args): ?string
    {
        switch ($provider) {
            case 'google':
                return $this->translate_with_google($text, $source, $target, $args);
            case 'deepl':
                return $this->translate_with_deepl($text, $source, $target, $args);
            default:
                /**
                 * Allow custom providers via filter.
                 *
                 * @param string|null $translation
                 * @param string      $text
                 * @param string      $source
                 * @param string      $target
                 * @param array       $args
                 */
                return apply_filters('fp_multilanguage_translate_with_' . $provider, null, $text, $source, $target, $args);
        }
    }

    private function translate_with_google(string $text, string $source, string $target, array $args): ?string
    {
        $options = Settings::get_options();
        $apiKey = $options['google_api_key'] ?? '';
        if ($apiKey === '') {
            return null;
        }

        $url = add_query_arg(['key' => $apiKey], 'https://translation.googleapis.com/language/translate/v2');
        $body = [
            'q' => $text,
            'source' => $source,
            'target' => $target,
            'format' => $args['format'] ?? 'text',
        ];

        $response = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return null;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (! isset($payload['data']['translations'][0]['translatedText'])) {
            return null;
        }

        return $payload['data']['translations'][0]['translatedText'];
    }

    private function translate_with_deepl(string $text, string $source, string $target, array $args): ?string
    {
        $options = Settings::get_options();
        $apiKey = $options['deepl_api_key'] ?? '';
        if ($apiKey === '') {
            return null;
        }

        $endpoint = $args['deepl_endpoint'] ?? 'https://api.deepl.com/v2/translate';
        $body = [
            'auth_key' => $apiKey,
            'text' => $text,
            'source_lang' => strtoupper($source),
            'target_lang' => strtoupper($target),
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return null;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (! isset($payload['translations'][0]['text'])) {
            return null;
        }

        return $payload['translations'][0]['text'];
    }

    private function lookup_manual_translation(string $text, string $target): ?string
    {
        $manualStrings = Settings::get_manual_strings();
        $key = $this->get_manual_key($text);
        if (isset($manualStrings[$key][$target])) {
            return $manualStrings[$key][$target];
        }

        return null;
    }

    private function manual_fallback(string $text, string $target, array $args): string
    {
        $fallbackLanguage = Settings::get_fallback_language();

        if ($target === $fallbackLanguage) {
            return $text;
        }

        $manualStrings = Settings::get_manual_strings();
        $key = $this->get_manual_key($text);
        if (isset($manualStrings[$key][$fallbackLanguage])) {
            return $manualStrings[$key][$fallbackLanguage];
        }

        /**
         * Filter the fallback translation when no provider is available.
         *
         * @param string $text
         * @param string $target
         * @param array  $args
         */
        return apply_filters('fp_multilanguage_translation_fallback', $text, $target, $args);
    }

    private function get_cache_key(string $text, string $source, string $target, array $args): string
    {
        $version = $this->get_cache_version();
        $argsKey = $args ? wp_json_encode($args) : '';

        return md5($version . '|' . $source . '|' . $target . '|' . $argsKey . '|' . $text);
    }

    private function get_cache_version(): string
    {
        if (! function_exists('get_option')) {
            return '1';
        }

        return (string) get_option(self::CACHE_VERSION_OPTION, '1');
    }

    private function get_cache(string $key)
    {
        if (isset(self::$runtimeCache[$key])) {
            return self::$runtimeCache[$key];
        }

        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($key, self::CACHE_GROUP);
            if ($cached !== false) {
                self::$runtimeCache[$key] = $cached;

                return $cached;
            }
        }

        if (function_exists('get_transient')) {
            $transient = get_transient(self::CACHE_GROUP . '_' . $key);
            if ($transient !== false) {
                self::$runtimeCache[$key] = $transient;

                return $transient;
            }
        }

        return false;
    }

    private function set_cache(string $key, string $value): void
    {
        self::$runtimeCache[$key] = $value;

        if (function_exists('wp_cache_set')) {
            wp_cache_set($key, $value, self::CACHE_GROUP, DAY_IN_SECONDS);
        }

        if (function_exists('set_transient')) {
            set_transient(self::CACHE_GROUP . '_' . $key, $value, DAY_IN_SECONDS);
        }
    }

    private function get_manual_key(string $text): string
    {
        return hash('sha1', $text);
    }

    private function get_quota_key(string $provider): string
    {
        return self::QUOTA_TRANSIENT_PREFIX . $provider;
    }

    private function is_quota_exceeded(string $provider): bool
    {
        $quota = $this->get_quota($provider);
        $limits = apply_filters('fp_multilanguage_quota_limits', [
            'requests' => 1000,
            'characters' => 500000,
        ], $provider);

        if ($quota['requests'] >= $limits['requests']) {
            return true;
        }

        if ($quota['characters'] >= $limits['characters']) {
            return true;
        }

        return false;
    }

    private function increment_quota(string $provider, int $chars): void
    {
        $quota = $this->get_quota($provider);
        $quota['requests']++;
        $quota['characters'] += $chars;

        $this->set_quota($provider, $quota);
    }

    private function get_quota(string $provider): array
    {
        if (function_exists('get_transient')) {
            $quota = get_transient($this->get_quota_key($provider));
            if (is_array($quota)) {
                return $quota;
            }
        }

        return [
            'requests' => 0,
            'characters' => 0,
        ];
    }

    private function set_quota(string $provider, array $quota): void
    {
        if (function_exists('set_transient')) {
            set_transient($this->get_quota_key($provider), $quota, DAY_IN_SECONDS);
        }
    }
}
