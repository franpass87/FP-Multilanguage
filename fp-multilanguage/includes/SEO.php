<?php
namespace FPMultilanguage\SEO;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Services\TranslationService;
use WP_Post;

class SEO
{
    private Settings $settings;

    private TranslationService $translationService;

    private PostTranslationManager $postTranslationManager;

    private const META_KEY = '_fp_multilanguage_seo';

    public function __construct(Settings $settings, TranslationService $translationService, PostTranslationManager $postTranslationManager)
    {
        $this->settings = $settings;
        $this->translationService = $translationService;
        $this->postTranslationManager = $postTranslationManager;
    }

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('add_meta_boxes', [$this, 'add_meta_box']);
            add_action('save_post', [$this, 'save_meta'], 20, 2);
            add_action('wp_head', [$this, 'render_meta_tags'], 1);
        }

        if (function_exists('add_filter')) {
            add_filter('pre_get_document_title', [$this, 'filter_document_title']);
            add_filter('wp_sitemaps_posts_entry', [$this, 'filter_sitemap_entry'], 10, 3);
        }
    }

    public function add_meta_box(): void
    {
        if (! function_exists('add_meta_box')) {
            return;
        }

        add_meta_box(
            'fp-multilanguage-seo',
            __('SEO multilingua', 'fp-multilanguage'),
            [$this, 'render_meta_box'],
            ['post', 'page'],
            'normal',
            'default'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        $meta = $this->get_meta($post->ID);
        $languages = array_unique(array_merge([
            Settings::get_source_language(),
        ], Settings::get_target_languages()));

        wp_nonce_field('fp_multilanguage_seo_meta', 'fp_multilanguage_seo_nonce');

        echo '<p>' . esc_html__('Personalizza title e description per ogni lingua.', 'fp-multilanguage') . '</p>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Lingua', 'fp-multilanguage') . '</th>';
        echo '<th>' . esc_html__('Meta title', 'fp-multilanguage') . '</th>';
        echo '<th>' . esc_html__('Meta description', 'fp-multilanguage') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($languages as $language) {
            $title = $meta['title'][$language] ?? '';
            $description = $meta['description'][$language] ?? '';
            echo '<tr>';
            echo '<td><code>' . esc_html($language) . '</code></td>';
            echo '<td><input type="text" class="widefat" name="fp_multilanguage_seo[title][' . esc_attr($language) . ']" value="' . esc_attr($title) . '" maxlength="160"></td>';
            echo '<td><textarea class="widefat" name="fp_multilanguage_seo[description][' . esc_attr($language) . ']" rows="2" maxlength="320">' . esc_textarea($description) . '</textarea></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function save_meta(int $postId, WP_Post $post): void
    {
        if (! isset($_POST['fp_multilanguage_seo_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fp_multilanguage_seo_nonce'])), 'fp_multilanguage_seo_meta')) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $raw = isset($_POST['fp_multilanguage_seo']) && is_array($_POST['fp_multilanguage_seo'])
            ? wp_unslash($_POST['fp_multilanguage_seo'])
            : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $sanitized = [
            'title' => [],
            'description' => [],
        ];

        foreach (['title', 'description'] as $field) {
            if (! isset($raw[$field]) || ! is_array($raw[$field])) {
                continue;
            }

            foreach ($raw[$field] as $language => $value) {
                $language = strtolower(sanitize_text_field($language));
                if ($language === '') {
                    continue;
                }

                if ($field === 'title') {
                    $sanitized[$field][$language] = sanitize_text_field($value);
                } else {
                    $sanitized[$field][$language] = sanitize_textarea_field($value);
                }
            }
        }

        update_post_meta($postId, self::META_KEY, $sanitized);
    }

    public function render_meta_tags(): void
    {
        if (! is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return;
        }

        $currentLanguage = $this->determine_language();
        $meta = $this->get_meta($post->ID);
        $translations = $this->postTranslationManager->get_post_translations($post->ID);

        $description = $meta['description'][$currentLanguage] ?? '';
        if ($description === '' && isset($translations[$currentLanguage]['excerpt'])) {
            $description = wp_strip_all_tags($translations[$currentLanguage]['excerpt']);
        }
        if ($description === '') {
            $description = wp_strip_all_tags($post->post_excerpt ?: $post->post_content);
        }

        $languageUrls = $this->get_language_urls($post);

        echo '<meta name="description" content="' . esc_attr(wp_trim_words($description, 55)) . '" />' . "\n";

        foreach ($languageUrls as $lang => $url) {
            echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($url) . '" />' . "\n";
        }

        $xDefault = Settings::get_source_language();
        if (isset($languageUrls[$xDefault])) {
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($languageUrls[$xDefault]) . '" />' . "\n";
        }
    }

    public function filter_document_title(string $title): string
    {
        if (! is_singular()) {
            return $title;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return $title;
        }

        $currentLanguage = $this->determine_language();
        $meta = $this->get_meta($post->ID);
        $translations = $this->postTranslationManager->get_post_translations($post->ID);

        if (isset($meta['title'][$currentLanguage]) && $meta['title'][$currentLanguage] !== '') {
            return $meta['title'][$currentLanguage];
        }

        if (isset($translations[$currentLanguage]['title']) && $translations[$currentLanguage]['title'] !== '') {
            return $translations[$currentLanguage]['title'];
        }

        $source = Settings::get_source_language();
        if ($currentLanguage !== $source) {
            $translated = $this->translationService->translate($post->post_title, $source, $currentLanguage);
            if ($translated !== '') {
                return $translated;
            }
        }

        return $title;
    }

    public function filter_sitemap_entry(array $entry, WP_Post $post, string $postType): array
    {
        unset($postType);

        $languageUrls = $this->get_language_urls($post);
        if (! empty($languageUrls)) {
            $entry['alternates'] = [];
            foreach ($languageUrls as $lang => $url) {
                $entry['alternates'][] = [
                    'language' => $lang,
                    'url' => $url,
                ];
            }
        }

        return $entry;
    }

    private function get_meta(int $postId): array
    {
        if (! function_exists('get_post_meta')) {
            return [
                'title' => [],
                'description' => [],
            ];
        }

        $stored = get_post_meta($postId, self::META_KEY, true);
        if (! is_array($stored)) {
            return [
                'title' => [],
                'description' => [],
            ];
        }

        return wp_parse_args($stored, [
            'title' => [],
            'description' => [],
        ]);
    }

    private function get_language_urls(WP_Post $post): array
    {
        if (! function_exists('get_permalink')) {
            return [];
        }

        $urls = [];
        $source = Settings::get_source_language();
        $urls[$source] = get_permalink($post);

        $translations = $this->postTranslationManager->get_post_translations($post->ID);
        foreach ($translations as $language => $data) {
            if (! isset($data['content']) || $data['content'] === '') {
                continue;
            }

            $urls[$language] = add_query_arg('fp_lang', $language, get_permalink($post));
        }

        return $urls;
    }

    private function determine_language(): string
    {
        /** @var string $language */
        $language = apply_filters('fp_multilanguage_current_language', '');
        if ($language !== '') {
            return strtolower($language);
        }

        return strtolower(Settings::get_source_language());
    }
}
