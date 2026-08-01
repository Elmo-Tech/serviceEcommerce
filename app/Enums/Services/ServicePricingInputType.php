<?php

declare(strict_types=1);

namespace App\Enums\Services;

enum ServicePricingInputType: int
{
    case SELECT = 0;
    case MULTI_SELECT = 1;
    case RADIO = 2;
    case CHECKBOX = 3;
}
