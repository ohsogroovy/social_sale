<?php

namespace App\Handlers;

use App\Clients\Shopify;
use App\Actions\AddProduct;

class CreateProductHandler
{
    public function __construct(private Shopify $shopify, private AddProduct $addProduct) {}

    public function handle(array $product): void
    {
        $product['seoDescription'] = $this->shopify->getProductMetafields($product['id'], ['key' => 'description_tag'])[0]['value'] ?? null;
        $this->addProduct->execute($product);
    }
}
