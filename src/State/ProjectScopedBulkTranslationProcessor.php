<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProjectScopedResource;
use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectScopedBulkTranslationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProjectScopedResource || !$data instanceof TranslatableResource) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();

        $data->touch($actor);
        $this->entityManager->persist($data);

        foreach ($data->getTranslations() as $translation) {
            if ($translation instanceof ResourceTranslation) {
                $translation->setTranslatable($data);
                $translation->touch($actor);
                $this->entityManager->persist($translation);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
