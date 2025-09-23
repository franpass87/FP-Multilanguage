<?php
namespace FPMultilanguage;

class CurrentLanguage
{
    public static function resolve(): string
    {
        $language = '';

        if (function_exists('get_query_var')) {
            $language = (string) get_query_var('fp_lang');
        }

        if ($language === '' && isset($_GET['fp_lang'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $language = (string) sanitize_text_field(wp_unslash($_GET['fp_lang'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ($language === '' && function_exists('determine_locale')) {
            $language = substr(determine_locale(), 0, 2);
        }

        $language = strtolower($language);

        if (function_exists('apply_filters')) {
            /** @var string $filtered */
            $filtered = apply_filters('fp_multilanguage_current_language', $language);
            if (is_string($filtered)) {
                $language = $filtered;
            }
        }

        return strtolower((string) $language);
    }
}
