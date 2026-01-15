<?php

namespace App\Clients;

use Iterator;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class Shopify
{
    private string $version = '2024-04';

    private PendingRequest $http;

    public function __construct()
    {
        $storeConfig = \config('services.shopify_store');
        $baseUrl = 'https://'.$storeConfig['name']."/admin/api/{$this->version}";

        $this->http = Http::withHeaders([
            'X-Shopify-Access-Token' => $storeConfig['access_token'],
        ])->acceptJson()->connectTimeout(60)->timeout(60)->retry(3, 30)->throw()->baseUrl($baseUrl);
    }

    public function productCount(): int
    {
        return $this->http->get('/products/count.json')->json('count');
    }

    public function getAllProductsWithSeoDescription(): Iterator
    {
        $afterCursor = '';
        $nextPage = true;
        while ($nextPage) {
            $query = "
            {
                products(first: 250 {$afterCursor}) {
                    edges {
                    node {
                        id
                        title
                        handle
                        tags
                        variants(first: 5) {
                            nodes {
                                id
                                title
                                sku
                                inventoryQuantity
                            }
                        }
                        featuredMedia {
                            preview {
                                image {
                                url
                                }
                            }
                        }
                        seo {
                            description
                        }
                    }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
            ";

            $response = $this->http->post('/graphql.json', [
                'query' => $query,
            ])->json('data.products');

            $afterCursor = "after: \"{$response['pageInfo']['endCursor']}\"";
            $nextPage = $response['pageInfo']['hasNextPage'];

            foreach (\array_column($response['edges'], 'node') as $product) {
                $cleanProduct = [
                    'id' => (int) filter_var($product['id'], FILTER_SANITIZE_NUMBER_INT),
                    'title' => $product['title'],
                    'handle' => $product['handle'],
                    'tags' => \implode(',', $product['tags']),
                    'images' => [['src' => $product['featuredMedia']['preview']['image']['url'] ?? null]],
                    'seoDescription' => $product['seo']['description'] ?? null,
                    'variants' => array_map(function ($variant) {
                        return [
                            'id' => (int) filter_var($variant['id'], FILTER_SANITIZE_NUMBER_INT),
                            'title' => $variant['title'],
                            'sku' => $variant['sku'],
                            'inventory_quantity' => $variant['inventoryQuantity'] ?? 0,
                        ];
                    }, $product['variants']['nodes']),
                ];

                yield $cleanProduct;
            }
        }
    }

    public function getProductByVariantSku(string $sku): ?array
    {
        $query = <<<Query
        {
            productVariants(first: 1, query: "sku:$sku") {
                nodes {
                    product {
                        id
                        title
                        tags
                        tracksInventory
                    }
                    inventoryQuantity
                }
            }
        }
        Query;

        $response = $this->http->post('/graphql.json', ['query' => $query])->json('data.productVariants.nodes');
        if (! $response) {
            return null;
        }
        $data = $response[0];

        return $data['product'] + ['variant' => ['inventoryQuantity' => $data['inventoryQuantity']]];

    }

    public function getProductMetafields(int $productId, array $params = []): array
    {
        return $this->http->get("/products/{$productId}/metafields.json".($params ? '?'.\http_build_query($params) : ''))->json('metafields');
    }

    public function webhookSubscriptions(int $count = 10): array
    {
        $query = <<<Query
        {
            webhookSubscriptions (first:{$count}) {
                edges {
                    node {
                        id
                        topic
                        endpoint {
                            __typename
                            ... on WebhookHttpEndpoint {
                              callbackUrl
                            }
                        }
                    }
                }
            }
          }
        Query;

        $webhookSubscriptions = $this->http->post('/graphql.json', \compact('query'))->json('data.webhookSubscriptions.edges');

        return \array_map(
            function ($webhook) {
                return [
                    'id' => $webhook['node']['id'],
                    'topic' => $webhook['node']['topic'],
                    'callbackUrl' => $webhook['node']['endpoint']['callbackUrl'],
                ];
            },
            $webhookSubscriptions
        );
    }

    public function createWebhookSubscription(string $topic, string $callbackUrl): string
    {
        $query = '
        mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
            webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
                userErrors {
                    field
                    message
                }
                webhookSubscription {
                    id
                    topic
                    format
                    endpoint {
                        __typename
                        ... on WebhookHttpEndpoint {
                            callbackUrl
                        }
                    }
                }
            }
        }
        ';
        $variables = [
            'topic' => $topic,
            'webhookSubscription' => [
                'callbackUrl' => $callbackUrl,
                'format' => 'JSON',
            ],
        ];

        return $this->http->post('/graphql.json', \compact('query', 'variables'))->json('data.webhookSubscriptionCreate.webhookSubscription.id');
    }

    public function deleteSubscription(string $id): bool
    {
        $query = '
        mutation webhookSubscriptionDelete($id: ID!) {
            webhookSubscriptionDelete(id: $id) {
              userErrors {
                field
                message
              }
              deletedWebhookSubscriptionId
            }
          }
        ';
        $variables = \compact('id');

        return $this->http->post('/graphql.json', \compact('query', 'variables'))->json('data.webhookSubscriptionDelete.deletedWebhookSubscriptionId') == $id;
    }

    public function addTagToProduct(int $productId, string $tag): array
    {
        $query = '
        mutation addTags($id: ID!, $tags: [String!]!) {
          tagsAdd(id: $id, tags: $tags) {
            node {
              id
            }
            userErrors {
              message
            }
          }
        }
        ';
        $variables = [
            'id' => "gid://shopify/Product/{$productId}",
            'tags' => [$tag],
        ];

        return $this->http->post('/graphql.json', \compact('query', 'variables'))->json('data.tagsAdd');
    }

    public function removeTagFromProduct(int $productId, string $tag): array
    {
        $query = '
        mutation removeTags($id: ID!, $tags: [String!]!) {
            tagsRemove(id: $id, tags: $tags) {
              node {
                id
              }
              userErrors {
                message
              }
            }
          }
        ';
        $variables = [
            'id' => "gid://shopify/Product/{$productId}",
            'tags' => ['trigger-'.$tag],
        ];

        return $this->http->post('/graphql.json', \compact('query', 'variables'))->json('data.tagsRemove');
    }
}
