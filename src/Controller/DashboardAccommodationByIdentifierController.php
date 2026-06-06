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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class DashboardAccommodationByIdentifierController
{
    private const SUPPORTED_LOCALES = ['fr', 'en', 'es', 'ar'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentUserProvider $currentUserProvider,
        private ProjectAccessGuard $projectAccessGuard,
        private SerializerInterface $serializer,
        private ResourceAutoTranslator $autoTranslator,
        private ResourceAutoFiller $autoFiller,
        private AccommodationStructuralPayloadApplier $structuralApplier,
        private AccommodationIdentifierGenerator $identifierGenerator,
    ) {
    }

    #[Route(
        '/api/projects/{projectId}/accommodations/{identifier}',
        name: 'api_project_accommodation_put_by_identifier',
        requirements: ['identifier' => '(?![0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})[A-Za-z0-9][A-Za-z0-9_-]*'],
        methods: ['PUT'],
        priority: 10
    )]
    public function __invoke(Request $request, string $projectId, string $identifier): Response
    {
        $project = $this->projectAccessGuard->getWritableProject($projectId);
        $accommodation = $this->findProjectAccommodation($project, $identifier);

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        if (array_key_exists('translations', $payload)) {
            throw new BadRequestHttpException('Use the /translations route to update multiple locales.');
        }

        $locale = $this->resolveLocale($request, $project);
        $accommodation->setCurrentLocale($locale);
        $this->structuralApplier->apply($accommodation, $project, $payload);

        if (!$this->identifierGenerator->isConsistentWith($accommodation)) {
            $accommodation->setIdentifier($this->identifierGenerator->generate($accommodation, $accommodation));
        }

        $this->autoTranslator->synchronize($accommodation, $project);

        $actor = $this->currentUserProvider->getUser();
        $accommodation->touch($actor);

        foreach ($accommodation->getTranslations() as $translation) {
            if (!$translation instanceof AccommodationTranslation) {
                continue;
            }
            $translation->setTranslatable($accommodation);
            if (!isset($payload['slug']) || $translation->getLocale() !== $locale) {
                $translation->recomputeSlug();
            }
            $this->autoFiller->fill($translation);
            $translation->touch($actor);
            $this->entityManager->persist($translation);
        }

        $this->entityManager->persist($accommodation);
        $this->entityManager->flush();

        return new JsonResponse(
            $this->serializer->serialize($accommodation, 'json', ['groups' => ['read'], 'locale' => $locale]),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
            true
        );
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

    private function findProjectAccommodation(Project $project, string $identifier): Accommodation
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
