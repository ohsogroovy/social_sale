<?php

namespace App\Http\Controllers;

use App\Clients\Shopify;
use App\Clients\HttpHeaders;
use Illuminate\Http\Request;

class ShopifyWebhookController extends Controller
{
    public static function getWebhooks()
    {
        return [
            [
                'name' => 'PRODUCTS_CREATE',
                'handler' => \App\Handlers\CreateProductHandler::class,
                'callback' => \secure_url('/api/shopify/webhook'),
            ],
            [
                'name' => 'PRODUCTS_DELETE',
                'handler' => \App\Handlers\DeleteProductHandler::class,
                'callback' => \secure_url('/api/shopify/webhook'),
            ],
            [
                'name' => 'PRODUCTS_UPDATE',
                'handler' => \App\Handlers\UpdateProductHandler::class,
                'callback' => \secure_url('/api/shopify/webhook'),
            ],
        ];
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $headers = $this->validateRequest($request);

        $topic = strtoupper(str_replace(['/', '.'], '_', $headers->get(HttpHeaders::X_SHOPIFY_TOPIC)));
        $shop = $headers->get(HttpHeaders::X_SHOPIFY_DOMAIN);
        $payload = \json_decode($request->getContent(), true);

        \logger()->info('Shopify webhook received.', \compact('topic'));

        // Getting handler.
        $handler = \array_values(\array_filter($this->getWebhooks(), fn ($webhook) => $webhook['name'] == $topic))[0]['handler'] ?? null;
        if ($handler) {
            $action = app($handler);
            $action->handle($payload);
        }

        return \response('');
    }

    private function validateRequest(Request $request): HttpHeaders
    {

        if (empty($request->getContent())) {
            \abort(401, 'No body was received when processing webhook');
        }

        $headers = new HttpHeaders($request->header());

        $missingHeaders = $headers->diff(
            [HttpHeaders::X_SHOPIFY_HMAC, HttpHeaders::X_SHOPIFY_TOPIC, HttpHeaders::X_SHOPIFY_DOMAIN],
            false,
        );
        if (! empty($missingHeaders)) {
            $missingHeaders = implode(', ', $missingHeaders);
            \abort(401, "Missing one or more of the required HTTP headers to process webhooks: [$missingHeaders]");
        }
        // validating hmac
        $hmac = $headers->get(HttpHeaders::X_SHOPIFY_HMAC);
        if ($hmac !== base64_encode(hash_hmac('sha256', $request->getContent(), \config('services.shopify_store.app.api_secret'), true))) {
            \abort(401, 'Could not validate webhook HMAC');
        }

        return $headers;
    }

    public function subscribeWebhooks(Shopify $shopifyClient): void
    {
        $registeredWebhooks = $shopifyClient->webhookSubscriptions();
        foreach (ShopifyWebhookController::getWebhooks() as $webhook) {
            if (\array_search($webhook['name'], \array_column($registeredWebhooks, 'topic')) === false) {
                $shopifyClient->createWebhookSubscription($webhook['name'], $webhook['callback']);
                \info("{$webhook['name']} webhook registered");
            }
        }
        dd('Done');
    }
}
