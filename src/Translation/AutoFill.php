<?php

namespace App\Translation;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AutoFill
{
    public function __construct(
        public readonly string $from,
        public readonly ?int $maxLength = null,
    ) {
    }
}
