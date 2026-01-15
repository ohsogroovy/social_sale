<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Variant;

class AddVariants
{
    public function execute(Product $product, array $variants): void
    {
        foreach ($variants as $variant) {
            $variantAttrs = [
                'shopify_id' => $variant['id'],
                'name' => $variant['title'],
                'sku' => $variant['sku'] ?? null,
                'quantity' => $variant['inventory_quantity'] ?? 0,
                'product_id' => $product->id,
            ];
            Variant::updateOrCreate(['shopify_id' => $variant['id']], $variantAttrs);
        }
    }
}
