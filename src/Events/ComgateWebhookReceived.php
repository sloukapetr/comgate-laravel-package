<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class ComgateWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly Request $request
    ) {
    }
}
