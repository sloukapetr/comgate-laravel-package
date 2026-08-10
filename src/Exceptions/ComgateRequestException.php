<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Exceptions;

use RuntimeException;

class ComgateRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody,
        private readonly ?int $apiCode = null
    ) {
        parent::__construct($message, $apiCode ?? $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }

    public function apiCode(): ?int
    {
        return $this->apiCode;
    }
}
