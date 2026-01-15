<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_search_product_by_sku(): void
    {
        Http::fake([
            'https://*.myshopify.com/admin/api/*' => Http::response([
                'data' => [
                    'productVariants' => [
                        'nodes' => [
                            [
                                'product' => [
                                    'id' => 'gid://shopify/Product/1234567890',
                                    'title' => 'Test Product',
                                    'tags' => ['trigger-Tag102', 'trigger-Tag2'],
                                ],
                                'inventoryQuantity' => 15,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/search-product?sku=TESTSKU123');

        $response->json('message');
        $this->assertStringContainsString('Test Product', $response->json('message'));
        $this->assertStringContainsString('Tag2', $response->json('message'));
    }

    public function test_search_product_by_sku_not_found(): void
    {
        Http::fake([
            'https://*.myshopify.com/admin/api/*' => Http::response([
                'data' => [
                    'productVariants' => [
                        'nodes' => [],
                    ],
                ],
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/search-product?sku=INVALIDSKU');

        $response->assertNotFound();
        $this->assertStringContainsString('No product found', $response->json('message'));
    }

    public function test_search_product_by_sku_tag_not_found(): void
    {
        Http::fake([
            'https://*.myshopify.com/admin/api/*' => Http::response([
                'data' => [
                    'productVariants' => [
                        'nodes' => [
                            [
                                'product' => [
                                    'id' => 'gid://shopify/Product/1234567890',
                                    'title' => 'Test Product',
                                    'tags' => [],
                                ],
                                'inventoryQuantity' => 15,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/search-product?sku=TESTSKU123');
        $response->assertStatus(404);
        $this->assertStringContainsString('does not have any trigger tags', $response->json('message'));
    }
}
