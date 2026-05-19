<?php

namespace App\Enum;

enum MediaStatus: string
{
    case PendingAnalysis = 'pending_analysis';
    case Ready = 'ready';
    case Archived = 'archived';
}
