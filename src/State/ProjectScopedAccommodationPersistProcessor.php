<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Accommodation;
use App\Security\ProjectAccessGuard;
use App\Translation\LocaleResolver;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ProjectScopedAccommodationPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private AccommodationAssociatedMediaPayloadApplier $associatedMediaApplier,
        private ProjectAccessGuard $projectAccessGuard,
        private LocaleResolver $localeResolver,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Accommodation) {
            $body = $this->extractBody();
            if (array_key_exists('associatedMedia', $body)) {
                $projectId = (string) ($uriVariables['projectId'] ?? '');
                $project = $this->projectAccessGuard->getWritableProject($projectId);
                $locale = $this->localeResolver->resolve($project);

                $this->associatedMediaApplier->apply($data, $project, $body, $locale);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
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

        return is_array($body) ? $body : [];
    }
}
