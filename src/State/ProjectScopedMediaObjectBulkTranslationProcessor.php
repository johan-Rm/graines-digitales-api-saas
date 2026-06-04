<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\MediaObject;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ProjectScopedMediaObjectBulkTranslationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof MediaObject) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();

        foreach ($this->extractTranslations() as $translationData) {
            $this->applyTranslation($data, $translationData, $actor);
        }

        $this->autoTranslator->synchronize($data, $project);

        foreach ($data->getTranslations() as $translation) {
            $translation->setTranslatable($data);
            $this->autoFiller->fill($translation);
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }

        $data->touch($actor);
        $this->entityManager->persist($data);

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function applyTranslation(MediaObject $data, mixed $translationData, mixed $actor): void
    {
        $locale = is_array($translationData) ? ($translationData['locale'] ?? '') : '';
        if (!is_string($locale) || '' === $locale) {
            return;
        }

        $translation = $data->getOrCreateTranslation($locale);

        foreach ($translationData as $key => $value) {
            if ('locale' === $key) {
                continue;
            }
            $setter = 'set'.ucfirst((string) $key);
            if (method_exists($translation, $setter)) {
                $translation->{$setter}($value);
            }
        }

        $translation->setTranslatable($data);
        $translation->touch($actor);
        $this->entityManager->persist($translation);
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
