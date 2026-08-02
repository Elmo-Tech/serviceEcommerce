<?php

declare(strict_types=1);

namespace App\Enums\Orders;

enum OrderStatus: int
{
    case PENDING = 0;
    case CONFIRMED = 1;
    case IN_PROGRESS = 2;
    case COMPLETED = 3;
    case CANCELLED = 4;
}
