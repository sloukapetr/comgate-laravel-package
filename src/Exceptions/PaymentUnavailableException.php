<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Exceptions;

use Sloukapetr\ComgateLaravelPackage\Data\ComgatePaymentAvailability;

class PaymentUnavailableException extends ComgateRequestException
{
    public function __construct(
        string $message,
        int $statusCode,
        string $responseBody,
        private readonly ComgatePaymentAvailability $availability,
        ?int $apiCode = null,
    ) {
        parent::__construct($message, $statusCode, $responseBody, $apiCode);
    }

    public function availability(): ComgatePaymentAvailability
    {
        return $this->availability;
    }
}