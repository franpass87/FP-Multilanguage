<?php
namespace FPMultilanguage\Content;

use FPMultilanguage\Admin\Settings;
use FPMultilanguage\CurrentLanguage;
use FPMultilanguage\Services\TranslationService;
use WP_Post;

class PostTranslationManager
{
    public const META_KEY = '_fp_multilanguage_translations';

    public const RELATION_META_KEY = '_fp_multilanguage_relations';

    private const HTML_TRANSLATION_ARGS = ['format' => 'html'];

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
            add_action('save_post', [$this, 'handle_post_save'], 20, 3);
            add_action('init', [$this, 'register_query_var']);
        }

        if (function_exists('add_filter')) {
            add_filter('the_content', [$this, 'filter_content']);
            add_filter('rest_prepare_post', [$this, 'expose_translations'], 10, 3);
        }
    }

    public function register_query_var(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        add_filter('query_vars', static function (array $vars): array {
            if (! in_array('fp_lang', $vars, true)) {
                $vars[] = 'fp_lang';
            }

            return $vars;
        });
    }

    public function handle_post_save(int $postId, WP_Post $post, bool $update): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'post' && $post->post_type !== 'page') {
            return;
        }

        $sourceLanguage = Settings::get_source_language();
        $targetLanguages = Settings::get_target_languages();

        $existingTranslations = $this->get_post_translations($postId);
        $hasChanges = false;

        if (Settings::is_auto_translate_enabled()) {
            foreach ($targetLanguages as $language) {
                if ($language === $sourceLanguage) {
                    continue;
                }

                $translatedContent = $this->translationService->translate(
                    $post->post_content,
                    $sourceLanguage,
                    $language,
                    self::HTML_TRANSLATION_ARGS
                );
                $translatedTitle = $this->translationService->translate($post->post_title, $sourceLanguage, $language);
                $translatedExcerpt = $post->post_excerpt ? $this->translationService->translate(
                    $post->post_excerpt,
                    $sourceLanguage,
                    $language,
                    self::HTML_TRANSLATION_ARGS
                ) : '';

                $existingTranslation = $existingTranslations[$language] ?? [];
                $currentTranslation = [
                    'title' => $translatedTitle,
                    'content' => $translatedContent,
                    'excerpt' => $translatedExcerpt,
                ];

                $previousTranslation = [
                    'title' => $existingTranslation['title'] ?? '',
                    'content' => $existingTranslation['content'] ?? '',
                    'excerpt' => $existingTranslation['excerpt'] ?? '',
                ];

                if ($currentTranslation !== $previousTranslation) {
                    $existingTranslations[$language] = $currentTranslation + ['updated_at' => time()];
                    $hasChanges = true;
                }
            }
        }

        if ($hasChanges) {
            $this->persist_translations($postId, $existingTranslations, $sourceLanguage);
        } else {
            $this->persist_relations($postId, $existingTranslations, $sourceLanguage);
        }
    }

    public function filter_content(string $content): string
    {
        if (is_admin()) {
            return $content;
        }

        $post = get_post();
        if (! $post instanceof WP_Post) {
            return $content;
        }

        $currentLanguage = $this->determine_language();
        $sourceLanguage = Settings::get_source_language();

        if ($currentLanguage === '' || $currentLanguage === $sourceLanguage) {
            return $content;
        }

        $translations = $this->get_post_translations($post->ID);
        if (isset($translations[$currentLanguage]['content'])) {
            return $translations[$currentLanguage]['content'];
        }

        $translated = $this->translationService->translate(
            $content,
            $sourceLanguage,
            $currentLanguage,
            self::HTML_TRANSLATION_ARGS
        );
        if ($translated !== '') {
            return $translated;
        }

        $fallbackLanguage = Settings::get_fallback_language();
        if ($fallbackLanguage !== $currentLanguage && isset($translations[$fallbackLanguage]['content'])) {
            return $translations[$fallbackLanguage]['content'];
        }

        return $content;
    }

    public function expose_translations($response, $post, $request)
    {
        if (isset($response->data) && is_array($response->data)) {
            $response->data['fp_multilanguage'] = [
                'language' => $this->determine_language() ?: Settings::get_source_language(),
                'translations' => $this->get_post_translations($post->ID),
            ];
        }

        return $response;
    }

    public function get_post_translations(int $postId): array
    {
        if (! function_exists('get_post_meta')) {
            return [];
        }

        $stored = get_post_meta($postId, self::META_KEY, true);
        if (! is_array($stored)) {
            return [];
        }

        return $stored;
    }

    public function get_relation(int $postId): array
    {
        if (! function_exists('get_post_meta')) {
            return [];
        }

        $stored = get_post_meta($postId, self::RELATION_META_KEY, true);
        if (! is_array($stored)) {
            return [];
        }

        return $stored;
    }

    private function determine_language(): string
    {
        return CurrentLanguage::resolve();
    }

    private function persist_translations(int $postId, array $translations, string $sourceLanguage): void
    {
        if (! function_exists('update_post_meta')) {
            return;
        }

        update_post_meta($postId, self::META_KEY, $translations);
        $this->persist_relations($postId, $translations, $sourceLanguage);
    }

    private function persist_relations(int $postId, array $translations, string $sourceLanguage): void
    {
        if (! function_exists('update_post_meta')) {
            return;
        }

        $relation = [
            'source' => $sourceLanguage,
            'languages' => array_keys($translations),
        ];

        update_post_meta($postId, self::RELATION_META_KEY, $relation);
    }
}
