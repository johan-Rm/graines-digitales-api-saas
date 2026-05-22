<?php

namespace App\Exception;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;

final class CategoryCodeInUseException extends \RuntimeException implements ProblemExceptionInterface
{
    public function __construct(private readonly int $accommodationCount)
    {
        parent::__construct('Cannot delete CategoryCode.');
    }

    public function getType(): string
    {
        return 'https://tools.ietf.org/html/rfc2616#section-10';
    }

    public function getTitle(): ?string
    {
        return 'Cannot delete CategoryCode';
    }

    public function getStatus(): ?int
    {
        return 409;
    }

    public function getDetail(): ?string
    {
        return sprintf('Ce code est utilise par %d bien(s) et ne peut pas etre supprime.', $this->accommodationCount);
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
