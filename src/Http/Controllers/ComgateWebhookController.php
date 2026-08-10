<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sloukapetr\ComgateLaravelPackage\ComgateService;
use Sloukapetr\ComgateLaravelPackage\Data\ComgateWebhookNotification;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentFailed;
use Sloukapetr\ComgateLaravelPackage\Events\ComgatePaymentPaid;
use Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived;
use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidWebhookSignatureException;

use function event;
use function response;

class ComgateWebhookController
{
    public function __invoke(Request $request, ComgateService $comgate): Response
    {
        $payload = $request->all();

        try {
            $validatedPayload = $comgate->handleWebhook($payload, $request->header('X-Comgate-Secret'));
        } catch (InvalidWebhookSignatureException) {
            return response('code=1&message=Invalid+secret', Response::HTTP_UNAUTHORIZED)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $notification = ComgateWebhookNotification::fromArray($validatedPayload);

        event(new ComgateWebhookReceived(
            $validatedPayload,
            $notification,
            $request
        ));

        if ($notification->isPaid()) {
            event(new ComgatePaymentPaid($validatedPayload, $notification, $request));
        } elseif ($notification->isCancelled()) {
            event(new ComgatePaymentFailed($validatedPayload, $notification, $request));
        }

        return response('code=0&message=OK', Response::HTTP_OK)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
