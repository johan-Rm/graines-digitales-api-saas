<?php

namespace App\Enum;

enum AiReviewStatus: string
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Suggested = 'suggested';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
