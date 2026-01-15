<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

class SmartCart
{
    private PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::acceptJson()->connectTimeout(60)->timeout(60)->retry(3, 30)->throw()->baseUrl(self::baseUrl());
    }

    protected static function baseUrl(): string
    {
        return config('services.smart_cart.base_url').'/api';
    }

    public function customer(array $filter): ?array
    {
        try {
            $response = $this->http->get('/customer', $filter);
        } catch (RequestException $e) { // @phpstan-ignore-line
            if ($e->response->notFound()) {

                return null;
            }
        }

        return $response->json('data');
    }

    public function reserveProduct(int $customerId, int $productId): array
    {
        return $this->http->post("/customer/{$customerId}/reserve-product", ['product_id' => $productId])->json();
    }

    public function addProductToWaitList(int $customerId, int $productId): array
    {
        return $this->http->post("/waitlists/{$customerId}/products", ['product_id' => $productId])->json();
    }

    public static function authorizationUrl(array $params): string
    {
        $query = http_build_query($params);

        return config('services.smart_cart.facebook_authorize_url')."?{$query}";
    }
}
