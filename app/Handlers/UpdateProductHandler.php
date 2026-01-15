<?php

namespace App\Handlers;

use App\Models\Product;
use App\Actions\AddTags;
use App\Clients\Shopify;
use App\Actions\AddVariants;

class UpdateProductHandler
{
    public function __construct(private Shopify $shopify, private AddTags $addTags, private AddVariants $addVariants) {}

    public function handle(array $product): void
    {
        $productId = $product['id'];
        /** @var Product */
        $localProduct = Product::where('shopify_id', $productId)->first();
        $productContainTriggerTag = \is_int(\strpos($product['tags'], 'trigger-'));

        if ($localProduct == null) {
            /** @var \App\Handlers\CreateProductHandler */
            $createProductHandler = app(CreateProductHandler::class);
            $createProductHandler->handle($product);

            return;
        }

        if (! $productContainTriggerTag) {
            logger()->info("Deleting product which doesn't contain trigger tag.", ['product_id' => $localProduct->id, 'shopify_id' => $productId]);
            $localProduct->delete();

            return;
        }

        $shortDescription = $this->shopify->getProductMetafields($productId, ['key' => 'description_tag'])[0]['value'] ?? null;
        $imageUrl = \reset($product['images'])['src'] ?? null;

        $localProduct->update([
            'name' => $product['title'],
            'handle' => $product['handle'],
            'image_url' => $imageUrl,
            'short_description' => $shortDescription,
        ]);
        $systemTag = $localProduct->tags()->where('is_system_tag', true)->first();
        $this->addTags->execute($localProduct, $product['tags'], $systemTag);
        $this->addVariants->execute($localProduct, $product['variants']);

        logger()->info('Updated product', ['product_id' => $localProduct->id, 'shopify_id' => $productId]);
    }
}
