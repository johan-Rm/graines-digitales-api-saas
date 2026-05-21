<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MediaObject;
use App\Message\AnalyzeMediaObject;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
final readonly class BulkMediaUploadController
{
    private const MAX_FILES = 20;

    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Request $request, string $projectId): JsonResponse
    {
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();
        $files = $this->extractFiles($request);

        if ([] === $files) {
            return new JsonResponse([
                'message' => 'At least one image file is required.',
                'created' => [],
                'errors' => [],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (count($files) > self::MAX_FILES) {
            return new JsonResponse([
                'message' => sprintf('A bulk upload accepts at most %d files.', self::MAX_FILES),
                'created' => [],
                'errors' => [],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $created = [];
        $errors = [];
        $finalAlt = $this->optionalString($request, 'finalAlt');
        $finalDescription = $this->optionalString($request, 'finalDescription');

        foreach ($files as $index => $file) {
            $mediaObject = new MediaObject();
            $mediaObject->setProject($project);
            $mediaObject->markCreatedBy($actor);
            $mediaObject->touch($actor);
            $mediaObject->setFile($file);
            $mediaObject->setFinalAlt($finalAlt);
            $mediaObject->setFinalDescription($finalDescription);
            $mediaObject->setChecksumSha256(hash_file('sha256', $file->getPathname()) ?: null);

            $violations = $this->validator->validate($mediaObject, null, ['Default', 'media:create']);

            if (count($violations) > 0) {
                $errors[] = [
                    'index' => $index,
                    'originalFilename' => $file->getClientOriginalName(),
                    'violations' => array_map(
                        static fn ($violation): array => [
                            'propertyPath' => $violation->getPropertyPath(),
                            'message' => $violation->getMessage(),
                        ],
                        iterator_to_array($violations),
                    ),
                ];

                continue;
            }

            $this->entityManager->persist($mediaObject);
            $this->entityManager->flush();
            $this->messageBus->dispatch(new AnalyzeMediaObject((string) $mediaObject->getId()));

            $created[] = [
                'index' => $index,
                'id' => (string) $mediaObject->getId(),
                'contentUrl' => '/media/'.$mediaObject->getFilePath(),
                'filePath' => $mediaObject->getFilePath(),
                'mimeType' => $mediaObject->getMimeType(),
                'size' => $mediaObject->getSize(),
                'originalFilename' => $mediaObject->getOriginalFilename(),
                'checksumSha256' => $mediaObject->getChecksumSha256(),
            ];
        }

        if ([] === $created) {
            return new JsonResponse([
                'message' => 'No image file could be uploaded.',
                'created' => [],
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'created' => $created,
            'errors' => $errors,
        ], [] === $errors ? Response::HTTP_CREATED : Response::HTTP_MULTI_STATUS);
    }

    /**
     * @return list<UploadedFile>
     */
    private function extractFiles(Request $request): array
    {
        $files = $request->files->all('files');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter(
                $files,
                static fn (mixed $file): bool => $file instanceof UploadedFile,
            ));
        }

        return [];
    }

    private function optionalString(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);

        return is_string($value) && '' !== trim($value) ? $value : null;
    }
}
