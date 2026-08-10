<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Exceptions;

use RuntimeException;

class ComgateRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }
}
