<?php

namespace App\Actions;

use App\Clients\Shopify;
use Symfony\Component\Console\Helper\ProgressBar;

class SyncProducts
{
    public function __construct(private Shopify $shopify, private AddProduct $addProduct) {}

    public function execute(?ProgressBar $progressBar = null): void
    {
        $products = $this->shopify->getAllProductsWithSeoDescription();
        foreach ($products as $product) {
            $progressBar?->advance();
            $this->addProduct->execute($product);
        }
        $progressBar?->finish();
    }
}
