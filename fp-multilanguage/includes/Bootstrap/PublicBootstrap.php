<?php
namespace FPMultilanguage\Bootstrap;

use FPMultilanguage\Blocks\LanguageSwitcherBlock;
use FPMultilanguage\Content\CommentTranslationManager;
use FPMultilanguage\Content\PostTranslationManager;
use FPMultilanguage\Content\TermTranslationManager;
use FPMultilanguage\Dynamic\DynamicStrings;
use FPMultilanguage\SEO\SEO;
use FPMultilanguage\Services\TranslationService;

class PublicBootstrap {
	private TranslationService $translationService;

	private PostTranslationManager $postTranslationManager;

	private CommentTranslationManager $commentTranslationManager;

	private TermTranslationManager $termTranslationManager;

	private DynamicStrings $dynamicStrings;

	private LanguageSwitcherBlock $languageSwitcherBlock;

	private SEO $seo;

	public function __construct(
		TranslationService $translationService,
		PostTranslationManager $postTranslationManager,
		CommentTranslationManager $commentTranslationManager,
		TermTranslationManager $termTranslationManager,
		DynamicStrings $dynamicStrings,
		LanguageSwitcherBlock $languageSwitcherBlock,
		SEO $seo
	) {
			$this->translationService        = $translationService;
			$this->postTranslationManager    = $postTranslationManager;
			$this->commentTranslationManager = $commentTranslationManager;
			$this->termTranslationManager    = $termTranslationManager;
			$this->dynamicStrings            = $dynamicStrings;
			$this->languageSwitcherBlock     = $languageSwitcherBlock;
			$this->seo                       = $seo;
	}

	public function register(): void {
			$this->translationService->register();
			$this->postTranslationManager->register();
			$this->commentTranslationManager->register();
			$this->termTranslationManager->register();
			$this->dynamicStrings->register();
			$this->languageSwitcherBlock->register();
			$this->seo->register();
	}
}
