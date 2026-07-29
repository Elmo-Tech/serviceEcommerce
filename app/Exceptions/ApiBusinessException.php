<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\HttpStatusCode;
use RuntimeException;

class ApiBusinessException extends RuntimeException
{
    public function __construct(
        private readonly string $translationKey,
        private readonly string $machineCode,
        private readonly HttpStatusCode $status,
        private readonly mixed $errors = null,
    ) {
        parent::__construct($machineCode);
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }

    public function machineCode(): string
    {
        return $this->machineCode;
    }

    public function status(): HttpStatusCode
    {
        return $this->status;
    }

    public function errors(): mixed
    {
        return $this->errors;
    }
}
