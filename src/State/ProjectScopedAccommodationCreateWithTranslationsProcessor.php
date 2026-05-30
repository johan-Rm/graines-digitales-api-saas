<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Creates an Accommodation with all its translations in a single request.
 *
 * The payload follows the same shape as POST /accommodations plus a `translations`
 * array. Top-level locale fields (name, label, etc.) set the current-locale
 * translation via the entity's virtual setters; the `translations` array then
 * updates/extends them, and auto-translate fills any missing locales.
 */
final readonly class ProjectScopedAccommodationCreateWithTranslationsProcessor implements ProcessorInterface
{
    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private AccommodationIdentifierGenerator $accommodationIdentifierGenerator,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
        private DenormalizerInterface $denormalizer,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Accommodation) {
            return null;
        }

        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();

        $data->setProject($project);
        $data->markCreatedBy($actor);

        if ('' === trim($data->getIdentifier())) {
            $data->setIdentifier($this->accommodationIdentifierGenerator->generate($data));
        }

        $data->ensureDefaultQualities();
        $data->touch($actor);
        $this->entityManager->persist($data);

        foreach ($this->extractTranslations() as $translationData) {
            $locale = is_array($translationData) ? ($translationData['locale'] ?? '') : '';
            if (!is_string($locale) || '' === $locale) {
                continue;
            }

            $translation = $data->getOrCreateTranslation($locale);
            $this->denormalizer->denormalize($translationData, $translation::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $translation,
                AbstractNormalizer::GROUPS => ['write'],
            ]);
            $translation->setTranslatable($data);
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }

        $this->autoTranslator->synchronize($data, $project);

        foreach ($data->getTranslations() as $translation) {
            $translation->setTranslatable($data);
            $this->autoFiller->fill($translation);
            if ($translation instanceof AccommodationTranslation) {
                $translation->recomputeSlug();
            }
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTranslations(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $body = json_decode((string) $request->getContent(), true);
        if (!is_array($body) || !is_array($body['translations'] ?? null)) {
            return [];
        }

        return array_values(array_filter($body['translations'], 'is_array'));
    }
}
