<?php

namespace App\Translation;

use App\Entity\Project;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class LocaleResolver
{
    private const SUPPORTED_LOCALES = ['fr', 'en', 'es', 'ar'];

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function resolve(Project $project): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->query->get('locale') ?: $request?->headers->get('Accept-Language');
        $locale = is_string($locale) && '' !== $locale ? substr($locale, 0, 2) : $project->getSourceLocale();

        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new BadRequestHttpException('Unsupported locale.');
        }

        if (!in_array($locale, $project->getEnabledLocales(), true)) {
            throw new BadRequestHttpException('Locale is not enabled for this project.');
        }

        return $locale;
    }
}
