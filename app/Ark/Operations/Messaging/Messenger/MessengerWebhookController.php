<?php

namespace App\Ark\Operations\Messaging\Messenger;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MessengerWebhookController
{
    public function __invoke(
        Request $request,
        MetaWebhookVerifier $verifier,
        MetaMessengerInboundParser $parser,
        MetaMessengerIngress $ingress,
        MetaMessengerReceiptIngress $receipts,
        MessengerShopPageResolver $pageResolver,
    ): Response|string {
        $platform = MetaMessengerPlatformConfiguration::current();

        if ($request->isMethod('GET')) {
            $challenge = $verifier->verifySubscription(
                $request,
                (string) ($platform->verifyToken() ?? ''),
            );

            if ($challenge === null) {
                return response('Forbidden', 403);
            }

            return response($challenge, 200);
        }

        // Signature first — before parse or tenant resolution.
        if (! $verifier->isValidSignature($request, $platform->appSecret())) {
            return response('Invalid signature.', 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ($payload['object'] ?? null) !== 'page') {
            return response('EVENT_RECEIVED', 200);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $pageId = trim((string) ($entry['id'] ?? ''));

            if ($pageId === '') {
                Log::info('messenger.webhook.entry_missing_page_id');

                continue;
            }

            $shop = $pageResolver->resolveByPageId($pageId);

            if ($shop === null) {
                $pageResolver->logUnknownPage($pageId);

                continue;
            }

            $configuration = MetaMessengerConfiguration::forShop($shop);

            if (! $configuration->isEnabled()) {
                continue;
            }

            foreach ($parser->parseReceiptsFromEntry($entry) as $receipt) {
                $receipts->ingest($receipt);
            }

            foreach ($parser->parseMessagesFromEntry($entry) as $messagePayload) {
                $ingress->ingest($messagePayload, $configuration);
            }

            MessengerHealth::rememberWebhookReceived($pageId);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
