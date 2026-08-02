<?php

declare(strict_types=1);

namespace App\Enums\Orders;

enum OrderPlace: int
{
    case WEBSITE = 0;
    case WHATSAPP = 1;
}
