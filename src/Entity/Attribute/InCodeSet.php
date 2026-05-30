<?php

declare(strict_types=1);

namespace App\Entity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class InCodeSet
{
    public function __construct(public readonly string $value) {}
}
