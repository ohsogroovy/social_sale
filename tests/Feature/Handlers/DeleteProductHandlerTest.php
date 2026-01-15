<?php

namespace Tests\Feature\Handlers;

use Tests\TestCase;
use App\Models\Product;
use App\Handlers\DeleteProductHandler;

class DeleteProductHandlerTest extends TestCase
{
    public function testDeleteProduct()
    {
        Product::factory()->create([
            'shopify_id' => 999,
            'name' => 'Product to Delete',
            'handle' => 'product-to-delete',
            'image_url' => 'https://example.com/product-to-delete-image.jpg',
            'short_description' => 'Description of product to delete',
        ]);

        $productData = [
            'id' => 999,
            'shopify_id' => 999,
        ];

        /** @var \App\Handlers\DeleteProductHandler $handler */
        $handler = app(DeleteProductHandler::class);
        $handler->handle($productData);

        $this->assertDatabaseMissing('products', [
            'shopify_id' => $productData['shopify_id'],
        ]);
    }
}
