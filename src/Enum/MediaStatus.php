<?php

namespace App\Enum;

enum MediaStatus: string
{
    case PendingWatermark = 'pending_watermark';
    case PendingAnalysis = 'pending_analysis';
    case Ready = 'ready';
    case Archived = 'archived';
}
