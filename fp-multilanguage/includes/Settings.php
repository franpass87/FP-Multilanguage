<?php
namespace FPMultilanguage\Admin;

use FPMultilanguage\Services\TranslationService;

class Settings
{
    public const OPTION_NAME = 'fp_multilanguage_options';

    public const MANUAL_STRINGS_OPTION = 'fp_multilanguage_manual_strings';

    private const DEFAULTS = [
        'providers' => ['google', 'deepl'],
        'google_api_key' => '',
        'deepl_api_key' => '',
        'target_languages' => ['en', 'it'],
        'source_language' => 'en',
        'fallback_language' => 'en',
        'auto_translate' => true,
    ];

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('admin_menu', [$this, 'register_options_page']);
            add_action('admin_init', [$this, 'register_settings']);
        }
    }

    public static function bootstrap_defaults(): void
    {
        if (! function_exists('get_option') || ! function_exists('update_option')) {
            return;
        }

        $options = get_option(self::OPTION_NAME, []);
        if (empty($options)) {
            update_option(self::OPTION_NAME, self::DEFAULTS);
        } else {
            update_option(self::OPTION_NAME, wp_parse_args($options, self::DEFAULTS));
        }

        if (false === get_option(self::MANUAL_STRINGS_OPTION, false)) {
            update_option(self::MANUAL_STRINGS_OPTION, []);
        }
    }

    public function register_options_page(): void
    {
        if (! function_exists('add_options_page')) {
            return;
        }

        add_options_page(
            __('FP Multilanguage', 'fp-multilanguage'),
            __('FP Multilanguage', 'fp-multilanguage'),
            'manage_options',
            'fp-multilanguage-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        if (! function_exists('register_setting')) {
            return;
        }

        register_setting(
            'fp_multilanguage_options_group',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => self::DEFAULTS,
            ]
        );

        add_settings_section(
            'fp_multilanguage_providers',
            __('Provider di traduzione', 'fp-multilanguage'),
            '__return_false',
            'fp-multilanguage-settings'
        );

        add_settings_field(
            'fp_multilanguage_provider_selection',
            __('Provider disponibili', 'fp-multilanguage'),
            [$this, 'render_provider_field'],
            'fp-multilanguage-settings',
            'fp_multilanguage_providers'
        );

        add_settings_field(
            'fp_multilanguage_google_api_key',
            __('Google Translate API Key', 'fp-multilanguage'),
            [$this, 'render_google_key_field'],
            'fp-multilanguage-settings',
            'fp_multilanguage_providers'
        );

        add_settings_field(
            'fp_multilanguage_deepl_api_key',
            __('DeepL API Key', 'fp-multilanguage'),
            [$this, 'render_deepl_key_field'],
            'fp-multilanguage-settings',
            'fp_multilanguage_providers'
        );

        add_settings_section(
            'fp_multilanguage_languages',
            __('Lingue e comportamento', 'fp-multilanguage'),
            '__return_false',
            'fp-multilanguage-settings'
        );

        add_settings_field(
            'fp_multilanguage_source_language',
            __('Lingua sorgente', 'fp-multilanguage'),
            [$this, 'render_source_language_field'],
            'fp-multilanguage-settings',
            'fp_multilanguage_languages'
        );

        add_settings_field(
            'fp_multilanguage_target_languages',
            __('Lingue di destinazione', 'fp-multilanguage'),
            [$this, 'render_target_languages_field'],
            'fp-multilanguage-settings',
            'fp_multilanguage_languages'
        );

        add_settings_field(
            'fp_multilanguage_fallback_language',
            __('Lingua di fallback', 'fp-multilanguage'),
            [$this, 'render_fallback_language_field'],
            'fp-multilanguage-settings',
            'fp_multilanguage_languages'
        );

        add_settings_field(
            'fp_multilanguage_auto_translate',
            __('Traduzione automatica dei contenuti', 'fp-multilanguage'),
            [$this, 'render_auto_translate_field'],
            'fp-multilanguage-settings',
            'fp_multilanguage_languages'
        );
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function sanitize_options($input): array
    {
        $sanitized = wp_parse_args(is_array($input) ? $input : [], self::DEFAULTS);

        $providers = array_map('sanitize_text_field', (array) ($sanitized['providers'] ?? []));
        $sanitized['providers'] = array_values(array_intersect($providers, ['google', 'deepl']));

        $sanitized['google_api_key'] = $this->sanitize_text($sanitized['google_api_key'] ?? '');
        $sanitized['deepl_api_key'] = $this->sanitize_text($sanitized['deepl_api_key'] ?? '');

        $sanitized['source_language'] = $this->sanitize_language($sanitized['source_language'] ?? self::DEFAULTS['source_language']);
        $sanitized['fallback_language'] = $this->sanitize_language($sanitized['fallback_language'] ?? self::DEFAULTS['fallback_language']);

        $targets = $sanitized['target_languages'] ?? self::DEFAULTS['target_languages'];
        if (is_string($targets)) {
            $targets = preg_split('/[,\s]+/', $targets);
        }
        $targets = array_filter(array_map([$this, 'sanitize_language'], (array) $targets));
        $sanitized['target_languages'] = array_values(array_unique($targets));

        $sanitized['auto_translate'] = (bool) ($sanitized['auto_translate'] ?? true);

        TranslationService::flush_cache();

        return $sanitized;
    }

    public function render_settings_page(): void
    {
        if (! function_exists('current_user_can') || ! current_user_can('manage_options')) {
            return;
        }

        $options = self::get_options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('FP Multilanguage', 'fp-multilanguage'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('fp_multilanguage_options_group');
                do_settings_sections('fp-multilanguage-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_provider_field(): void
    {
        $options = self::get_options();
        $providers = $options['providers'];
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[providers][]" value="google" <?php checked(in_array('google', $providers, true)); ?>>
            <?php esc_html_e('Google Translate', 'fp-multilanguage'); ?>
        </label><br>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[providers][]" value="deepl" <?php checked(in_array('deepl', $providers, true)); ?>>
            <?php esc_html_e('DeepL', 'fp-multilanguage'); ?>
        </label>
        <?php
    }

    public function render_google_key_field(): void
    {
        $options = self::get_options();
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[google_api_key]" value="<?php echo esc_attr($options['google_api_key']); ?>">
        <p class="description"><?php esc_html_e('Chiave API di Google Translate. Necessaria per le richieste di traduzione automatiche.', 'fp-multilanguage'); ?></p>
        <?php
    }

    public function render_deepl_key_field(): void
    {
        $options = self::get_options();
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[deepl_api_key]" value="<?php echo esc_attr($options['deepl_api_key']); ?>">
        <p class="description"><?php esc_html_e('Chiave API di DeepL (selezionare quella corretta per server EU/US).', 'fp-multilanguage'); ?></p>
        <?php
    }

    public function render_source_language_field(): void
    {
        $options = self::get_options();
        ?>
        <input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[source_language]" value="<?php echo esc_attr($options['source_language']); ?>" maxlength="10">
        <p class="description"><?php esc_html_e('Codice lingua predefinito dei contenuti (es. en, it).', 'fp-multilanguage'); ?></p>
        <?php
    }

    public function render_fallback_language_field(): void
    {
        $options = self::get_options();
        ?>
        <input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[fallback_language]" value="<?php echo esc_attr($options['fallback_language']); ?>" maxlength="10">
        <p class="description"><?php esc_html_e('Lingua utilizzata come fallback manuale quando i provider non sono disponibili.', 'fp-multilanguage'); ?></p>
        <?php
    }

    public function render_target_languages_field(): void
    {
        $options = self::get_options();
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[target_languages]" value="<?php echo esc_attr(implode(', ', $options['target_languages'])); ?>">
        <p class="description"><?php esc_html_e('Inserire l’elenco di lingue separate da virgola (es. it, fr, de).', 'fp-multilanguage'); ?></p>
        <?php
    }

    public function render_auto_translate_field(): void
    {
        $options = self::get_options();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[auto_translate]" value="1" <?php checked($options['auto_translate']); ?>>
            <?php esc_html_e('Traduci automaticamente i post al salvataggio.', 'fp-multilanguage'); ?>
        </label>
        <?php
    }

    public static function get_options(): array
    {
        if (! function_exists('get_option')) {
            return self::DEFAULTS;
        }

        $options = get_option(self::OPTION_NAME, []);

        return wp_parse_args(is_array($options) ? $options : [], self::DEFAULTS);
    }

    public static function get_manual_strings(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        $stored = get_option(self::MANUAL_STRINGS_OPTION, []);
        if (! is_array($stored)) {
            return [];
        }

        return $stored;
    }

    public static function update_manual_string(string $key, string $language, string $value): void
    {
        if (! function_exists('update_option')) {
            return;
        }

        $strings = self::get_manual_strings();
        if (! isset($strings[$key]) || ! is_array($strings[$key])) {
            $strings[$key] = [];
        }

        $strings[$key][$language] = $value;
        update_option(self::MANUAL_STRINGS_OPTION, $strings);
    }

    public static function get_enabled_providers(): array
    {
        $options = self::get_options();

        return $options['providers'];
    }

    public static function get_source_language(): string
    {
        $options = self::get_options();

        return $options['source_language'];
    }

    public static function get_fallback_language(): string
    {
        $options = self::get_options();

        return $options['fallback_language'];
    }

    public static function get_target_languages(): array
    {
        $options = self::get_options();

        return $options['target_languages'];
    }

    public static function is_auto_translate_enabled(): bool
    {
        $options = self::get_options();

        return (bool) $options['auto_translate'];
    }

    private function sanitize_text(string $value): string
    {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($value);
        }

        return trim(strip_tags((string) $value));
    }

    private function sanitize_language(string $value): string
    {
        $value = strtolower($this->sanitize_text($value));

        return preg_replace('/[^a-z0-9_-]/', '', $value);
    }
}
