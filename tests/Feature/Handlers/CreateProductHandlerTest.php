<?php

namespace Tests\Feature\Handlers;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Handlers\CreateProductHandler;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateProductHandlerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function testCreateNewProduct()
    {
        $productData = [
            'id' => 123,
            'title' => 'Test Product',
            'handle' => 'test-product',
            'tags' => 'tag1, tag2',
            'shopify_id' => 123,
            'images' => [
                ['src' => 'https://example.com/image.jpg'],
            ],
            'variants' => [
                ['id' => 1, 'title' => 'Variant 1', 'sku' => 'V1'],
            ],
        ];

        Http::fake([
            // Mocking the Shopify metafields request
            '*/products/*/metafields.json*' => Http::response([
                'metafields' => [
                    ['key' => 'description_tag', 'value' => 'A short description'],
                ],
            ], 200),
        ]);
        /** @var \App\Handlers\CreateProductHandler $handler */
        $handler = app(CreateProductHandler::class);
        $handler->handle($productData);
        $this->assertDatabaseHas('products', [
            'shopify_id' => $productData['id'],
            'name' => $productData['title'],
            'handle' => $productData['handle'],
        ]);
    }
}
