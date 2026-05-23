<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Entity\MediaObject;
use App\Entity\ProjectScopedResource;
use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;
use App\Message\AnalyzeMediaObject;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ProjectScopedPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ProjectScopedResource) {
            $projectId = (string) ($uriVariables['projectId'] ?? '');
            $project = $this->projectAccessGuard->getWritableProject($projectId);
            $actor = $this->currentUserProvider->getUser();

            if (null === $data->getProject()) {
                $data->setProject($project);
                $data->markCreatedBy($actor);
            }

            if ($data instanceof Accommodation) {
                $data->ensureDefaultQualities();
            }

            $data->touch($actor);
            $this->entityManager->persist($data);

            if ($data instanceof TranslatableResource) {
                foreach ($data->getTranslations() as $translation) {
                    if ($translation instanceof ResourceTranslation) {
                        $translation->setTranslatable($data);
                        $translation->touch($actor);
                        $this->entityManager->persist($translation);
                    }
                }
            }

            if ($data instanceof MediaObject && null !== $data->getFile()) {
                $data->setChecksumSha256(hash_file('sha256', $data->getFile()->getPathname()) ?: null);
            }
        }

        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        if ($data instanceof MediaObject) {
            $this->messageBus->dispatch(new AnalyzeMediaObject((string) $data->getId()));
        }

        return $result;
    }
}
