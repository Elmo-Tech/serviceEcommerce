<?php

declare(strict_types=1);

namespace App\Enums\Services;

enum ServiceMediaType: int
{
    case IMAGE = 0;
    case VIDEO = 1;
}
