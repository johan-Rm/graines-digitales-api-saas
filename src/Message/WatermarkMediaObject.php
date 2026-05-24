<?php

namespace App\Message;

final readonly class WatermarkMediaObject
{
    public function __construct(public string $mediaObjectId)
    {
    }
}
