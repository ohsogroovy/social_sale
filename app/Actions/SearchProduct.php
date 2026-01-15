<?php

namespace App\Actions;

use App\Clients\Shopify;

class SearchProduct
{
    public function __construct(private Shopify $shopifyClient) {}

    public function execute(string $sku): array
    {
        $product = $this->shopifyClient->getProductByVariantSku($sku);
        if (! $product) {
            return [
                'error' => true,
                'message' => 'No product found with this SKU.',
                'status' => 404,
            ];
        }

        $tags = array_map('trim', $product['tags']);
        $tags = array_unique(array_filter($tags));
        $filteredTriggerTags = array_filter($tags, fn ($tag) => str_starts_with($tag, 'trigger-'));

        $filteredTriggerTags = array_map(fn ($tag) => trim(substr($tag, 8)), $filteredTriggerTags);
        $filteredTriggerTags = array_filter($filteredTriggerTags);

        if (empty($filteredTriggerTags)) {
            return [
                'error' => true,
                'message' => 'This product does not have any trigger tags.',
                'status' => 404,
            ];
        }

        rsort($filteredTriggerTags);
        $shortestTag = $filteredTriggerTags[0];

        $productName = $product['title'];
        $tracksInventory = $product['tracksInventory'] ?? false;
        $quantity = $product['variant']['inventoryQuantity'] ?? 0;
        $productShopifyId = preg_replace('/\D/', '', $product['id']);

        return [
            'error' => false,
            'data' => [
                'shortestTag' => $shortestTag,
                'productName' => $productName,
                'tracksInventory' => $tracksInventory,
                'quantity' => $quantity,
                'productShopifyId' => $productShopifyId,
            ],
            'status' => 200,
        ];
    }
}
