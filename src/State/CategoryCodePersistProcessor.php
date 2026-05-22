<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CategoryCode;
use App\Entity\Project;
use App\Entity\ResourceTranslation;
use App\Exception\CategoryCodeAlreadyExistsException;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CategoryCodePersistProcessor implements ProcessorInterface
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
        if (!$data instanceof CategoryCode) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $project = $this->projectAccessGuard->getWritableProject((string) ($uriVariables['projectId'] ?? ''));

        if ($this->categoryCodeExists($data, $project)) {
            throw new CategoryCodeAlreadyExistsException();
        }

        $actor = $this->currentUserProvider->getUser();
        $data->setProject($project);
        $data->markCreatedBy($actor);
        $data->touch($actor);
        $this->entityManager->persist($data);

        foreach ($data->getTranslations() as $translation) {
            if ($translation instanceof ResourceTranslation) {
                $translation->setTranslatable($data);
                $translation->touch($actor);
                $this->entityManager->persist($translation);
            }
        }

        try {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        } catch (UniqueConstraintViolationException) {
            throw new CategoryCodeAlreadyExistsException();
        }
    }

    private function categoryCodeExists(CategoryCode $categoryCode, Project $project): bool
    {
        return null !== $this->entityManager->getRepository(CategoryCode::class)->findOneBy([
            'project' => $project,
            'inCodeSet' => $categoryCode->getInCodeSet(),
            'code' => $categoryCode->getCode(),
        ]);
    }
}
