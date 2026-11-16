<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Exceptions;

use Throwable;

final class ComgateConnectionException extends ComgateRequestException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, '', null, $previous);
    }
}
