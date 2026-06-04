<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\AccommodationMedia;
use App\Entity\MediaObject;
use App\Entity\MediaObjectTranslation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class AccommodationMediaNormalizer implements NormalizerInterface
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
        $data = $this->objectNormalizer->normalize($object, $format, $context);

        if (!is_array($data) || !$object instanceof AccommodationMedia) {
            return $data;
        }

        $locale = $context['locale'] ?? $this->requestLocale();
        if (is_string($locale)) {
            $caption = $this->resolveCaption($object, $locale);
            if (null !== $caption) {
                $data['caption'] = $caption;
            }
        }

        if (in_array('read:with-translations', $context['groups'] ?? [], true)) {
            $data['translations'] = $this->buildTranslations($object);
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AccommodationMedia;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [AccommodationMedia::class => true];
    }

    private function requestLocale(): ?string
    {
        $locale = $this->requestStack->getCurrentRequest()?->query->get('locale');

        return is_string($locale) && '' !== $locale ? $locale : null;
    }

    /**
     * @return list<array{locale: string, caption: string|null}>
     */
    private function buildTranslations(AccommodationMedia $accommodationMedia): array
    {
        $mediaObject = $accommodationMedia->getMediaObject();
        if (!$mediaObject instanceof MediaObject) {
            return [];
        }

        $translations = [];
        foreach ($mediaObject->getTranslations() as $translation) {
            if ($translation instanceof MediaObjectTranslation) {
                $translations[] = [
                    'locale' => $translation->getLocale(),
                    'caption' => $translation->getCaption(),
                ];
            }
        }

        return $translations;
    }

    private function resolveCaption(AccommodationMedia $accommodationMedia, string $locale): ?string
    {
        $mediaObject = $accommodationMedia->getMediaObject();
        if (!$mediaObject instanceof MediaObject) {
            return null;
        }

        foreach ($mediaObject->getTranslations() as $translation) {
            if ($translation instanceof MediaObjectTranslation && $translation->getLocale() === $locale) {
                return $translation->getCaption();
            }
        }

        return null;
    }
}
