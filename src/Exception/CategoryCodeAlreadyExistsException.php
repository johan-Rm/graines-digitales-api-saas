<?php

namespace App\Exception;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;

final class CategoryCodeAlreadyExistsException extends \RuntimeException implements ProblemExceptionInterface
{
    public function __construct()
    {
        parent::__construct('CategoryCode already exists.');
    }

    public function getType(): string
    {
        return 'https://tools.ietf.org/html/rfc2616#section-10';
    }

    public function getTitle(): ?string
    {
        return 'CategoryCode already exists';
    }

    public function getStatus(): ?int
    {
        return 409;
    }

    public function getDetail(): ?string
    {
        return 'Ce code existe deja dans ce jeu de codes pour ce projet.';
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
