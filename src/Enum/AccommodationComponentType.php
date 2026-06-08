<?php

namespace App\Enum;

enum AccommodationComponentType: string
{
    case Tryptique = 'SCREEN_ACCOMMODATION_TRYPTIQUE';
    case Overlay = 'SCREEN_ACCOMMODATION_OVERLAY';
    case Full = 'SCREEN_ACCOMMODATION_FULL';
    case Split = 'SCREEN_ACCOMMODATION_SPLIT';
    case Carousel = 'SCREEN_ACCOMMODATION_CAROUSEL';
    case Duo = 'SCREEN_ACCOMMODATION_DUO';
    case Contact = 'SCREEN_ACCOMMODATION_CONTACT';
}
