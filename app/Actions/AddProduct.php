<?php

namespace App\Actions;

use App\Models\Product;

class AddProduct
{
    public function __construct(private AddTags $addTags, private AddVariants $addVariants) {}

    public function execute(array $product): void
    {
        $productId = $product['id'];
        $imageUrl = \reset($product['images'])['src'] ?? null;
        $variants = $product['variants'];

        $productContainTriggerTag = \is_int(\strpos($product['tags'], 'trigger-'));
        $hasValidVariant = \array_filter($variants, fn ($variant) => ! empty($variant['sku'] ?? ''));
        if (! $productContainTriggerTag && ! $hasValidVariant) {
            logger()->info("Skipping product which doesn't contain trigger tag and has no valid variant.", ['shopify_id' => $productId]);

            return;
        }

        $localProduct = Product::updateOrCreate(
            ['shopify_id' => $productId],
            [
                'name' => $product['title'],
                'handle' => $product['handle'],
                'image_url' => $imageUrl,
                'short_description' => $product['seoDescription'],
            ]
        );

        $this->addTags->execute($localProduct, $product['tags']);
        $this->addVariants->execute($localProduct, $product['variants']);
    }
}
