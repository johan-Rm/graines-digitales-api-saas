<?php

namespace App\Message;

final readonly class AnalyzeMediaObject
{
    public function __construct(public string $mediaObjectId)
    {
    }
}
