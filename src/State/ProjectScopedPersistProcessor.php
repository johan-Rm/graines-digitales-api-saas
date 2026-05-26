<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Entity\MediaObject;
use App\Entity\ProjectScopedResource;
use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;
use App\Message\WatermarkMediaObject;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ProjectScopedPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private AccommodationIdentifierGenerator $accommodationIdentifierGenerator,
        #[Autowire(env: 'WEBAPPS_DIR')]
        private string $webappsDir,
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
                if ('' === trim($data->getIdentifier())) {
                    $data->setIdentifier($this->accommodationIdentifierGenerator->generate($data));
                }
                $data->ensureDefaultQualities();
            }

            $data->touch($actor);
            $this->entityManager->persist($data);

            if ($data instanceof TranslatableResource) {
                foreach ($data->getTranslations() as $translation) {
                    if ($translation instanceof ResourceTranslation) {
                        $translation->setTranslatable($data);
                        if ($translation instanceof AccommodationTranslation) {
                            $translation->recomputeSlug();
                        }
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

        if ($data instanceof MediaObject && null !== $data->getFilePath()) {
            $this->copyToOriginals($data);
            $this->messageBus->dispatch(new WatermarkMediaObject((string) $data->getId()));
        }

        return $result;
    }

    private function copyToOriginals(MediaObject $mediaObject): void
    {
        $mediaPath = $mediaObject->getProject()?->getMediaPath();
        if (null === $mediaPath) {
            return;
        }

        $webappsDir = rtrim($this->webappsDir, '/');
        $source = $webappsDir.'/'.$mediaPath.'/'.$mediaObject->getFilePath();
        $originalsDir = $webappsDir.'/'.$mediaPath.'/originals';

        if (!file_exists($source)) {
            return;
        }

        if (!is_dir($originalsDir)) {
            mkdir($originalsDir, 0777, true);
        }

        copy($source, $originalsDir.'/'.$mediaObject->getFilePath());
    }
}
