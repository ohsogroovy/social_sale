<?php

namespace Tests\Feature\Handlers;

use Tests\TestCase;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use App\Handlers\UpdateProductHandler;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UpdateProductHandlerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function testUpdateExistingProduct()
    {
        $existingProduct = Product::factory()->create([
            'shopify_id' => 123,
            'name' => 'Old Product',
            'handle' => 'old-product',
            'image_url' => 'https://example.com/old-image.jpg',
            'short_description' => 'Old description',
        ]);

        $productData = [
            'id' => 123,
            'title' => 'Updated Product',
            'handle' => 'updated-product',
            'tags' => 'tag1, trigger-new-tag',
            'variants' => [
                ['id' => 1, 'title' => 'Variant 1', 'sku' => 'V1'],
            ],
            'images' => [
                ['src' => 'https://example.com/new-image.jpg'],
            ],
        ];

        Http::fake([
            '/products/*/metafields.json*' => Http::response([
                'metafields' => [
                    ['key' => 'description_tag', 'value' => 'Updated description'],
                ],
            ], 200),
        ]);

        /** @var \App\Handlers\UpdateProductHandler $handler */
        $handler = app(UpdateProductHandler::class);
        $handler->handle($productData);

        $this->assertDatabaseHas('products', [
            'shopify_id' => $productData['id'],
            'name' => $productData['title'],
            'handle' => $productData['handle'],
            'short_description' => 'Updated description',
            'image_url' => 'https://example.com/new-image.jpg',
        ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'new-tag',
            'product_id' => $existingProduct->id,
        ]);

        $this->assertDatabaseMissing('tags', [
            'name' => 'old-tag',
            'product_id' => $existingProduct->id,
        ]);
    }

    public function testDeleteProductWhenTriggerTagNotPresent()
    {
        $existingProduct = Product::factory()->create([
            'shopify_id' => 125,
            'name' => 'Product to Delete',
            'handle' => 'delete-product',
            'image_url' => 'https://example.com/delete-product-image.jpg',
            'short_description' => 'Description to delete',
        ]);

        $productData = [
            'id' => 125,
            'title' => 'Updated Product',
            'handle' => 'updated-delete-product',
            'tags' => 'tag1, tag2', // No trigger tag
            'images' => [
                ['src' => 'https://example.com/updated-delete-product-image.jpg'],
            ],
        ];

        /** @var \App\Handlers\UpdateProductHandler $handler */
        $handler = app(UpdateProductHandler::class);
        $handler->handle($productData);
        $this->assertDatabaseMissing('products', [
            'shopify_id' => $productData['id'],
        ]);
        $this->assertDatabaseMissing('tags', [
            'product_id' => $existingProduct->id,
        ]);
    }
}
