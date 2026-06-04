<?php

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

final readonly class ProjectScopedAccommodationBulkTranslationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
        private DenormalizerInterface $denormalizer,
        private RequestStack $requestStack,
        private AccommodationStructuralPayloadApplier $structuralApplier,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Accommodation) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();

        $this->structuralApplier->apply($data, $project, $this->extractBody());

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
            if (!$translation instanceof AccommodationTranslation) {
                continue;
            }
            $translation->setTranslatable($data);
            $this->autoFiller->fill($translation);
            $translation->recomputeSlug();
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }

        $data->touch($actor);
        $this->entityManager->persist($data);

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTranslations(): array
    {
        $body = $this->extractBody();

        if (!is_array($body['translations'] ?? null)) {
            return [];
        }

        return array_values(array_filter($body['translations'], 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractBody(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $body = json_decode((string) $request->getContent(), true);

        return is_array($body) ? $body : [];
    }
}
