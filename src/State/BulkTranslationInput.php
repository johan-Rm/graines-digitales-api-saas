<?php

namespace App\State;

final class BulkTranslationInput
{
    public string $resourceId = '';

    /** @var array<int, array<string, mixed>> */
    public array $translations = [];
}
