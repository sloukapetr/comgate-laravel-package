<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Exceptions;

use RuntimeException;
use Throwable;

class ComgateRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody,
        private readonly ?int $apiCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $apiCode ?? $statusCode, $previous);
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

    /**
     * Connection failures and HTTP 5xx responses are worth retrying.
     */
    public function isRetryable(): bool
    {
        return $this instanceof ComgateConnectionException || $this->statusCode >= 500;
    }
}
