<?php

namespace App\State;

use Symfony\Component\Serializer\Attribute\Groups;

final class BulkTranslationInput
{
    #[Groups(['write:translations'])]
    public string $resourceId = '';

    /** @var array<int, array<string, mixed>> */
    #[Groups(['write:translations'])]
    public array $translations = [];
}
