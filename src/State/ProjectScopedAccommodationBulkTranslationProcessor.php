<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\AccommodationAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectScopedAccommodationBulkTranslationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private AccommodationAutoTranslator $autoTranslator,
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

        $this->autoTranslator->synchronize($data, $project);

        $data->touch($actor);
        $this->entityManager->persist($data);

        foreach ($data->getTranslations() as $translation) {
            if ($translation instanceof AccommodationTranslation) {
                $translation->setTranslatable($data);
                $translation->recomputeSlug();
                $translation->touch($actor);
                $this->entityManager->persist($translation);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
