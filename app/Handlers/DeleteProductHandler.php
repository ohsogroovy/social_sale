<?php

namespace App\Handlers;

use App\Models\Product;

class DeleteProductHandler
{
    public function handle(array $product): void
    {
        logger()->info('Deleting product', ['product_id' => $product['id']]);
        $productModel = Product::where('shopify_id', $product['id'])->first();
        if (! $productModel) {
            logger()->warning('Product not found. Skipping deletion.', ['shopify_id' => $product['id']]);

            return;
        }

        $productModel->tags()->delete();
        $productModel->delete();
    }
}
