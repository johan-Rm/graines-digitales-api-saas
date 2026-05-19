<?php

namespace App\MessageHandler;

use App\Message\AnalyzeMediaObject;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AnalyzeMediaObjectHandler
{
    public function __invoke(AnalyzeMediaObject $message): void
    {
        // Placeholder for the future AI workflow. Suggestions must remain human-reviewed.
    }
}
