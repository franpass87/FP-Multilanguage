<?php
namespace FPMultilanguage\Dynamic;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\TranslationService;

class DynamicStrings
{
    private TranslationService $translationService;

    private Settings $settings;

    public function __construct(TranslationService $translationService, Settings $settings)
    {
        $this->translationService = $translationService;
        $this->settings = $settings;
    }

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('init', [$this, 'register_assets']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_fp_multilanguage_save_string', [$this, 'handle_ajax_save']);
        }

        if (function_exists('add_filter')) {
            add_filter('widget_title', [$this, 'translate_dynamic_string'], 10, 3);
            add_filter('widget_text', [$this, 'translate_dynamic_string']);
            add_filter('nav_menu_item_title', [$this, 'translate_menu_item'], 10, 4);
            add_filter('gettext', [$this, 'translate_gettext'], 10, 3);
        }
    }

    public function register_assets(): void
    {
        if (! function_exists('wp_register_script')) {
            return;
        }

        wp_register_script(
            'fp-multilanguage-dynamic',
            FP_MULTILANGUAGE_URL . 'assets/js/dynamic-translations.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    public function enqueue_assets(): void
    {
        if (! function_exists('wp_enqueue_script')) {
            return;
        }

        if (! wp_script_is('fp-multilanguage-dynamic', 'registered')) {
            $this->register_assets();
        }

        $manualStrings = Settings::get_manual_strings();
        $language = $this->get_current_language();
        $canEdit = function_exists('current_user_can') ? current_user_can('manage_options') : false;

        wp_enqueue_script('fp-multilanguage-dynamic');
        wp_localize_script(
            'fp-multilanguage-dynamic',
            'fpMultilanguageDynamic',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fp_multilanguage_manual_string'),
                'language' => $language,
                'manualStrings' => $manualStrings,
                'canEdit' => $canEdit,
                'prompts' => [
                    'edit' => __('Inserisci la traduzione manuale per questa stringa:', 'fp-multilanguage'),
                ],
            ]
        );
    }

    public function translate_dynamic_string($title, $instance = null, $id_base = '')
    {
        unset($instance, $id_base);

        return $this->translate_string($title);
    }

    public function translate_menu_item($title, $item, $args, $depth)
    {
        unset($item, $args, $depth);

        return $this->translate_string($title);
    }

    public function translate_gettext($translation, $text, $domain, $args = [])
    {
        if (! is_array($args)) {
            $args = [];
        }

        if (! is_string($text) || trim($text) === '') {
            return $translation;
        }

        $language = $this->get_current_language();
        $source = Settings::get_source_language();

        if ($language === '' || $language === $source) {
            return $translation;
        }

        $manualStrings = Settings::get_manual_strings();
        $key = $this->get_manual_key($text);
        if (isset($manualStrings[$key][$language])) {
            return $manualStrings[$key][$language];
        }

        $forceTranslation = ! empty($args['force']) || ! empty($args['force_translation']);
        $serviceArgs = isset($args['service_args']) && is_array($args['service_args'])
            ? $args['service_args']
            : [];

        if (function_exists('apply_filters')) {
            $filterResult = apply_filters(
                'fp_multilanguage_force_gettext_translation',
                [
                    'force' => $forceTranslation,
                    'service_args' => $serviceArgs,
                ],
                $translation,
                $text,
                $domain,
                $language,
                $source,
                $args
            );

            if (is_array($filterResult)) {
                if (array_key_exists('force', $filterResult)) {
                    $forceTranslation = (bool) $filterResult['force'];
                }
                if (isset($filterResult['service_args']) && is_array($filterResult['service_args'])) {
                    $serviceArgs = $filterResult['service_args'];
                }
            } else {
                $forceTranslation = (bool) $filterResult;
            }
        }

        if (! $forceTranslation && is_string($translation) && $translation !== '' && $translation !== $text) {
            return $translation;
        }

        return $this->translate_string($text, $serviceArgs);
    }

    public function handle_ajax_save(): void
    {
        if (! function_exists('check_ajax_referer')) {
            wp_send_json_error(['message' => 'missing_wordpress_functions'], 400);
        }

        check_ajax_referer('fp_multilanguage_manual_string', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        $key = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $value = isset($_POST['value']) ? wp_kses_post(wp_unslash($_POST['value'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ($key === '' || $language === '') {
            wp_send_json_error(['message' => 'invalid_parameters'], 400);
        }

        Settings::update_manual_string($key, $language, $value);
        TranslationService::flush_cache();

        wp_send_json_success(['key' => $key, 'language' => $language, 'value' => $value]);
    }

    private function translate_string($text, array $args = [])
    {
        if (! is_string($text) || trim($text) === '') {
            return $text;
        }

        $language = $this->get_current_language();
        $source = Settings::get_source_language();

        if ($language === '' || $language === $source) {
            return $text;
        }

        $manualStrings = Settings::get_manual_strings();
        $key = $this->get_manual_key($text);
        if (isset($manualStrings[$key][$language])) {
            return $manualStrings[$key][$language];
        }

        $translated = $this->translationService->translate($text, $source, $language, $args);

        return $translated !== '' ? $translated : $text;
    }

    private function get_current_language(): string
    {
        $language = CurrentLanguage::resolve();
        if ($language !== '') {
            return $language;
        }

        return strtolower(Settings::get_source_language());
    }

    private function get_manual_key(string $text): string
    {
        return hash('sha1', $text);
    }
}
