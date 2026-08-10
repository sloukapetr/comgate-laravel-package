<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Sloukapetr\ComgateLaravelPackage\Data\ComgateWebhookNotification;

class ComgateWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ComgateWebhookNotification $notification,
        public readonly Request $request
    ) {
    }
}
