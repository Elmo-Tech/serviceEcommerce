<?php

declare(strict_types=1);

namespace App\Enums\Orders;

enum DiscountType: int
{
    case FIXED = 0;
    case PERCENTAGE = 1;
}
