<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Accommodation;
use App\Entity\AccommodationTranslation;
use App\Entity\Project;
use App\Security\CurrentUserProvider;
use App\Security\ProjectAccessGuard;
use App\State\AccommodationIdentifierGenerator;
use App\State\AccommodationStructuralPayloadApplier;
use App\Translation\ResourceAutoFiller;
use App\Translation\ResourceAutoTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsController]
final readonly class AccommodationTranslationsByIdentifierController
{
    private const SUPPORTED_LOCALES = ['fr', 'en', 'es', 'ar'];

    public function __construct(
        private ProjectAccessGuard $projectAccessGuard,
        private CurrentUserProvider $currentUserProvider,
        private EntityManagerInterface $entityManager,
        private DenormalizerInterface $denormalizer,
        private SerializerInterface $serializer,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
        private AccommodationStructuralPayloadApplier $structuralApplier,
        private AccommodationIdentifierGenerator $identifierGenerator,
    ) {
    }

    #[Route(
        '/api/projects/{projectId}/accommodations/{identifier}/translations',
        name: 'api_project_accommodation_get_translations_by_identifier',
        requirements: ['identifier' => '(?![0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})[A-Za-z0-9][A-Za-z0-9_-]*'],
        methods: ['GET'],
        priority: 10
    )]
    public function getTranslations(string $projectId, string $identifier): Response
    {
        $project = $this->projectAccessGuard->getReadableProject($projectId);
        $accommodation = $this->findAccommodation($project, $identifier);
        $accommodation->setCurrentLocale($project->getSourceLocale());

        return new JsonResponse(
            $this->serializer->serialize($accommodation, 'json', ['groups' => ['read', 'read:with-translations']]),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
            true
        );
    }

    #[Route(
        '/api/projects/{projectId}/accommodations/{identifier}/translations',
        name: 'api_project_accommodation_put_translations_by_identifier',
        requirements: ['identifier' => '(?![0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})[A-Za-z0-9][A-Za-z0-9_-]*'],
        methods: ['PUT'],
        priority: 10
    )]
    public function __invoke(Request $request, string $projectId, string $identifier): Response
    {
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $accommodation = $this->findAccommodation($project, $identifier);
        $actor = $this->currentUserProvider->getUser();

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        $locale = $this->resolveLocale($request, $project);
        $accommodation->setResolvedLocale($locale, $locale, null);
        $this->structuralApplier->apply($accommodation, $project, $payload);

        if (!$this->identifierGenerator->isConsistentWith($accommodation)) {
            $accommodation->setIdentifier($this->identifierGenerator->generate($accommodation, $accommodation));
        }

        $this->applyTranslations($accommodation, $payload['translations'] ?? []);
        $this->autoTranslator->synchronize($accommodation, $project);
        $this->finalizeTranslations($accommodation);

        $accommodation->touch($actor);
        $accommodation->setCurrentLocale($project->getSourceLocale());
        $this->entityManager->persist($accommodation);
        $this->entityManager->flush();

        return new JsonResponse(
            $this->serializer->serialize($accommodation, 'json', ['groups' => ['read', 'read:with-translations']]),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
            true
        );
    }

    /**
     * @param array<mixed> $translations
     */
    private function applyTranslations(Accommodation $accommodation, array $translations): void
    {
        foreach ($translations as $translationData) {
            $locale = is_array($translationData) ? ($translationData['locale'] ?? '') : '';
            if (!is_string($locale) || '' === $locale) {
                continue;
            }

            $translation = $accommodation->getOrCreateTranslation($locale);

            $this->denormalizer->denormalize($translationData, $translation::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $translation,
                AbstractNormalizer::GROUPS => ['write'],
            ]);

            $translation->setTranslatable($accommodation);
            $this->entityManager->persist($translation);
        }
    }

    private function finalizeTranslations(Accommodation $accommodation): void
    {
        $actor = $this->currentUserProvider->getUser();

        foreach ($accommodation->getTranslations() as $translation) {
            if (!$translation instanceof AccommodationTranslation) {
                continue;
            }
            $translation->setTranslatable($accommodation);
            $this->autoFiller->fill($translation);
            $translation->recomputeSlug();
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }
    }

    private function resolveLocale(Request $request, Project $project): string
    {
        $queryLocale = $request->query->get('locale');
        $locale = $queryLocale;
        if (!is_string($locale) || '' === $locale) {
            $locale = $request->headers->get('Accept-Language');
        }
        $locale = is_string($locale) && '' !== $locale ? substr($locale, 0, 2) : $project->getSourceLocale();

        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            throw new BadRequestHttpException('Unsupported locale.');
        }

        if (!in_array($locale, $project->getEnabledLocales(), true)) {
            throw new BadRequestHttpException('Locale is not enabled for this project.');
        }

        return $locale;
    }

    private function findAccommodation(Project $project, string $identifier): Accommodation
    {
        $accommodation = $this->entityManager->getRepository(Accommodation::class)->findOneBy([
            'project' => $project,
            'identifier' => $identifier,
        ]);

        if (!$accommodation instanceof Accommodation) {
            throw new NotFoundHttpException('Accommodation not found.');
        }

        return $accommodation;
    }
}
