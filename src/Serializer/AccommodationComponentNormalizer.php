<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\AccommodationComponent;
use App\Entity\Project;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Resolves the localized fields (headline, text, name) of an AccommodationComponent
 * for the requested locale, falling back to the project source locale when the
 * requested translation is missing. Structural fields are locale-agnostic.
 */
final readonly class AccommodationComponentNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $objectNormalizer,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, mixed>|\ArrayObject<string, mixed>|bool|float|int|string|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|\ArrayObject|bool|float|int|string|null
    {
        if ($object instanceof AccommodationComponent) {
            $this->resolveLocale($object, $context);
        }

        return $this->objectNormalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AccommodationComponent;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [AccommodationComponent::class => true];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveLocale(AccommodationComponent $component, array $context): void
    {
        $requested = $this->requestedLocale($component, $context);

        $current = null !== $component->getTranslation($requested)
            ? $requested
            : $this->sourceLocale($component, $requested);
        $fallback = $current !== $requested ? $current : null;

        $component->setResolvedLocale($requested, $current, $fallback);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function requestedLocale(AccommodationComponent $component, array $context): string
    {
        $accommodation = $component->getAccommodation();
        if (null !== $accommodation) {
            return $accommodation->getRequestedLocale();
        }

        $contextLocale = $context['locale'] ?? null;
        if (is_string($contextLocale) && '' !== $contextLocale) {
            return $contextLocale;
        }

        $queryLocale = $this->requestStack->getCurrentRequest()?->query->get('locale');
        if (is_string($queryLocale) && '' !== $queryLocale) {
            return $queryLocale;
        }

        return $component->getCurrentLocale();
    }

    private function sourceLocale(AccommodationComponent $component, string $fallback): string
    {
        $project = $component->getProject();

        return $project instanceof Project ? $project->getSourceLocale() : $fallback;
    }
}
