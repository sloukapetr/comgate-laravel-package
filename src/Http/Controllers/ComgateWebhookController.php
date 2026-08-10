<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Sloukapetr\ComgateLaravelPackage\ComgateService;
use Sloukapetr\ComgateLaravelPackage\Events\ComgateWebhookReceived;
use Sloukapetr\ComgateLaravelPackage\Exceptions\InvalidWebhookSignatureException;

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

        event(new ComgateWebhookReceived($validatedPayload, $request));

        return response('code=0&message=OK', Response::HTTP_OK)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
