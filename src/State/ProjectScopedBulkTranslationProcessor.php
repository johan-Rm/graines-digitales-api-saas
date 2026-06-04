<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CategoryCode;
use App\Entity\ProjectScopedResource;
use App\Entity\ResourceTranslation;
use App\Entity\TranslatableResource;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final readonly class ProjectScopedBulkTranslationProcessor implements ProcessorInterface
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
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProjectScopedResource || !$data instanceof TranslatableResource) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $projectId = (string) ($uriVariables['projectId'] ?? '');
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $actor = $this->currentUserProvider->getUser();
        $body = $this->extractBody();

        if ($data instanceof CategoryCode && array_key_exists('text', $body)) {
            $text = $body['text'];
            $data->setText(is_string($text) ? $text : null);
        }

        foreach ($this->extractTranslations($body) as $translationData) {
            $locale = is_array($translationData) ? ($translationData['locale'] ?? '') : '';
            if (!is_string($locale) || '' === $locale) {
                continue;
            }

            if ($data instanceof CategoryCode && array_key_exists('label', $translationData)) {
                throw new BadRequestHttpException('CategoryCode label cannot be updated.');
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
            if ($translation instanceof ResourceTranslation) {
                $translation->setTranslatable($data);
                $this->autoFiller->fill($translation);
                $translation->touch($actor);
                $this->entityManager->persist($translation);
            }
        }

        $data->touch($actor);
        $this->entityManager->persist($data);

        $this->entityManager->flush();

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTranslations(array $body): array
    {
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
        if (!is_array($body)) {
            return [];
        }

        return $body;
    }
}
