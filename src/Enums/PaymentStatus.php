<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';
    case Authorized = 'AUTHORIZED';

    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::Cancelled;
    }

    public function allowsRefund(): bool
    {
        return $this === self::Paid;
    }

    public function allowsCancel(): bool
    {
        return $this === self::Pending;
    }

    public function allowsCapture(): bool
    {
        return $this === self::Authorized;
    }
}
