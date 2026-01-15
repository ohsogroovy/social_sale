<?php

namespace Tests\Feature\Controllers;

use Mockery;
use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Variant;
use App\Clients\Shopify;
use App\Actions\SearchProduct;
use App\Actions\AssignTriggerNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The authenticated user for testing.
     */
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createAuthenticatedUser();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createAuthenticatedUser(): User
    {
        $user = User::factory()->create([
            'facebook_user_id' => 123456,
            'facebook_page_id' => 78910,
            'facebook_user_token' => 'test_user_token',
            'facebook_page_token' => 'test_page_token',
            'auto_trigger' => false,
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_search_products_returns_product_info_with_tags(): void
    {
        // Create product with variant and tags
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'shopify_id' => '12345678',
        ]);

        // Create tag for the product
        $tag = new Tag(['name' => 'tag1']);
        $product->tags()->save($tag);

        // Create variant with quantity
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-123',
            'quantity' => 5,
        ]);

        // Mock SearchProduct action
        $mockSearchProduct = Mockery::mock(SearchProduct::class);
        $mockSearchProduct->shouldReceive('execute')
            ->with('TEST-SKU-123')
            ->once()
            ->andReturn([
                'error' => false,
                'data' => [
                    'shortestTag' => 'tag1',
                    'productName' => 'Test Product',
                    'tracksInventory' => true,
                    'quantity' => 5,
                    'productShopifyId' => '12345678',
                ],
                'status' => 200,
            ]);
        $this->app->instance(SearchProduct::class, $mockSearchProduct);

        // Mock AssignTriggerNumber and Shopify for dependency injection
        $this->mock(AssignTriggerNumber::class);
        $this->mock(Shopify::class);

        // Test the endpoint
        $response = $this->getJson('/search-product?sku=TEST-SKU-123');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ])
            ->assertJson([
                'message' => '"Test Product" "tag1". Only 5 left! Reply to this comment to purchase.',
                'data' => [
                    'shortestTag' => 'tag1',
                ],
            ]);
    }

    public function test_search_products_returns_error_when_product_not_found(): void
    {
        // Mock SearchProduct action to return an error
        $mockSearchProduct = Mockery::mock(SearchProduct::class);
        $mockSearchProduct->shouldReceive('execute')
            ->with('NON-EXISTENT-SKU')
            ->once()
            ->andReturn([
                'error' => true,
                'message' => 'No product found with this SKU.',
                'status' => 404,
            ]);
        $this->app->instance(SearchProduct::class, $mockSearchProduct);

        // Mock AssignTriggerNumber and Shopify for dependency injection
        $this->mock(AssignTriggerNumber::class);
        $this->mock(Shopify::class);

        // Test with non-existent SKU
        $response = $this->getJson('/search-product?sku=NON-EXISTENT-SKU');

        $response->assertStatus(404)
            ->assertJson(['message' => 'No product found with this SKU.']);
    }

    public function test_search_products_returns_message_when_product_has_no_tags(): void
    {
        // Mock SearchProduct action to return a product without tags
        $mockSearchProduct = Mockery::mock(SearchProduct::class);
        $mockSearchProduct->shouldReceive('execute')
            ->with('TEST-SKU-456')
            ->once()
            ->andReturn([
                'error' => true,
                'message' => 'This product does not have any tags.',
                'status' => 200,
            ]);
        $this->app->instance(SearchProduct::class, $mockSearchProduct);

        // Mock AssignTriggerNumber and Shopify for dependency injection
        $this->mock(AssignTriggerNumber::class);
        $this->mock(Shopify::class);

        // Test the endpoint
        $response = $this->getJson('/search-product?sku=TEST-SKU-456');

        $response->assertStatus(200)
            ->assertJson(['message' => 'This product does not have any tags.']);
    }

    public function test_search_products_with_high_quantity_shows_10_plus(): void
    {
        // Create product with variant and tags
        $product = Product::factory()->create([
            'name' => 'High Quantity Product',
            'shopify_id' => '87654321',
        ]);

        // Mock SearchProduct action
        $mockSearchProduct = Mockery::mock(SearchProduct::class);
        $mockSearchProduct->shouldReceive('execute')
            ->with('HIGH-QTY-SKU')
            ->once()
            ->andReturn([
                'error' => false,
                'data' => [
                    'shortestTag' => 'high-qty',
                    'productName' => 'High Quantity Product',
                    'tracksInventory' => true,
                    'quantity' => 15,
                    'productShopifyId' => '87654321',
                ],
                'status' => 200,
            ]);
        $this->app->instance(SearchProduct::class, $mockSearchProduct);

        // Mock AssignTriggerNumber and Shopify for dependency injection
        $this->mock(AssignTriggerNumber::class);
        $this->mock(Shopify::class);

        // Test the endpoint
        $response = $this->getJson('/search-product?sku=HIGH-QTY-SKU');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ])
            ->assertSee('10+ left', false);
    }

    public function test_search_products_with_auto_trigger_enabled(): void
    {
        // Create user with auto_trigger enabled
        $this->user->update(['auto_trigger' => true]);

        // Create product with variant and tags
        $product = Product::factory()->create([
            'name' => 'Auto Trigger Product',
            'shopify_id' => '11223344',
        ]);

        // Mock SearchProduct action
        $mockSearchProduct = Mockery::mock(SearchProduct::class);
        $mockSearchProduct->shouldReceive('execute')
            ->with('AUTO-TRIGGER-SKU')
            ->once()
            ->andReturn([
                'error' => false,
                'data' => [
                    'shortestTag' => 'auto-tag',
                    'productName' => 'Auto Trigger Product',
                    'tracksInventory' => true,
                    'quantity' => 7,
                    'productShopifyId' => '11223344',
                ],
                'status' => 200,
            ]);
        $this->app->instance(SearchProduct::class, $mockSearchProduct);

        // Mock AssignTriggerNumber action
        $mockAssignTrigger = Mockery::mock(AssignTriggerNumber::class);
        $mockAssignTrigger->shouldReceive('execute')
            ->once()
            ->andReturn('T123');
        $this->app->instance(AssignTriggerNumber::class, $mockAssignTrigger);

        // Mock Shopify for dependency injection
        $this->mock(Shopify::class);

        // Test the endpoint
        $response = $this->getJson('/search-product?sku=AUTO-TRIGGER-SKU');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
                'autoTrigger',
            ])
            ->assertJson([
                'autoTrigger' => [
                    'sku' => 'AUTO-TRIGGER-SKU',
                    'productName' => 'Auto Trigger Product',
                    'triggerTag' => 'T123',
                    'quantity' => 7,
                ],
            ]);
    }

    public function test_search_products_without_inventory_tracking(): void
    {
        // Mock SearchProduct action
        $mockSearchProduct = Mockery::mock(SearchProduct::class);
        $mockSearchProduct->shouldReceive('execute')
            ->with('NO-INVENTORY-SKU')
            ->once()
            ->andReturn([
                'error' => false,
                'data' => [
                    'shortestTag' => 'no-inv',
                    'productName' => 'No Inventory Tracking',
                    'tracksInventory' => false,
                    'quantity' => 0,
                    'productShopifyId' => '99887766',
                ],
                'status' => 200,
            ]);
        $this->app->instance(SearchProduct::class, $mockSearchProduct);

        // Mock AssignTriggerNumber and Shopify for dependency injection
        $this->mock(AssignTriggerNumber::class);
        $this->mock(Shopify::class);

        // Test the endpoint
        $response = $this->getJson('/search-product?sku=NO-INVENTORY-SKU');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ])
            ->assertJsonMissing([
                'Only 0 left',
                '10+ left',
            ]);
    }

    public function test_search_products_validation_error(): void
    {
        // Mock dependencies
        $this->mock(SearchProduct::class);
        $this->mock(AssignTriggerNumber::class);
        $this->mock(Shopify::class);

        // Test with missing SKU parameter
        $response = $this->getJson('/search-product');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }
}
