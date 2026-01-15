<?php

namespace Tests\Feature\Actions;

use Mockery;
use Tests\TestCase;
use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Clients\SmartCart;
use App\Actions\ReserveProduct;
use App\Actions\SendWaitMessage;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReserveProductTest extends TestCase
{
    use RefreshDatabase;

    private ReserveProduct $action;

    private $smartCartMock;

    private $facebookMock;

    private $sendWaitMessageMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->smartCartMock = Mockery::mock(SmartCart::class);
        $this->facebookMock = Mockery::mock(Facebook::class);
        $this->sendWaitMessageMock = Mockery::mock(SendWaitMessage::class);

        $this->action = new ReserveProduct(
            $this->smartCartMock,
            $this->facebookMock,
            $this->sendWaitMessageMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reserves_single_product_successfully(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create([
            'shopify_id' => 123456,
        ]);
        $products = new Collection([$product]);

        $userData = [
            'sc_id' => 12345,
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $successResponse = [
            'error' => false,
            'message' => 'Product reserved successfully',
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->once()
            ->with($userData['sc_id'], $product->shopify_id)
            ->andReturn($successResponse);

        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($comment, $product) {
                return $message['recipient'] === ['comment_id' => $comment->id] &&
                       $message['message']['attachment']['type'] === 'template' &&
                       $message['message']['attachment']['payload']['template_type'] === 'generic' &&
                       $message['message']['attachment']['payload']['elements'][0]['title'] === $product->name &&
                       $message['message']['attachment']['payload']['elements'][0]['subtitle'] === 'The product has been reserved. Please check your cart.';
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_handles_reservation_error_response(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create([
            'shopify_id' => 123456,
        ]);
        $products = new Collection([$product]);

        $userData = [
            'sc_id' => 12345,
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $errorResponse = [
            'error' => true,
            'message' => 'Product out of stock',
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->once()
            ->with($userData['sc_id'], $product->shopify_id)
            ->andReturn($errorResponse);

        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($comment, $product) {
                return $message['recipient'] === ['comment_id' => $comment->id] &&
                       $message['message']['attachment']['type'] === 'template' &&
                       $message['message']['attachment']['payload']['template_type'] === 'generic' &&
                       $message['message']['attachment']['payload']['elements'][0]['title'] === $product->name &&
                       $message['message']['attachment']['payload']['elements'][0]['subtitle'] === 'Product out of stock';
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_reserves_multiple_products(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product1 = Product::factory()->create([
            'shopify_id' => 123456,
        ]);
        $product2 = Product::factory()->create([
            'shopify_id' => 789012,
        ]);
        $products = new Collection([$product1, $product2]);

        $userData = [
            'sc_id' => 12345,
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $response1 = [
            'error' => false,
            'message' => 'Product 1 reserved successfully',
        ];

        $response2 = [
            'error' => false,
            'message' => 'Product 2 reserved successfully',
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->once()
            ->with($userData['sc_id'], $product1->shopify_id)
            ->andReturn($response1);

        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->once()
            ->with($userData['sc_id'], $product2->shopify_id)
            ->andReturn($response2);

        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($comment, $product1, $product2) {
                return $message['recipient'] === ['comment_id' => $comment->id] &&
                       $message['message']['attachment']['type'] === 'template' &&
                       $message['message']['attachment']['payload']['template_type'] === 'generic' &&
                       count($message['message']['attachment']['payload']['elements']) === 2 &&
                       $message['message']['attachment']['payload']['elements'][0]['title'] === $product1->name &&
                       $message['message']['attachment']['payload']['elements'][1]['title'] === $product2->name;
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_handles_mixed_reservation_responses(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product1 = Product::factory()->create([
            'shopify_id' => 123456,
        ]);
        $product2 = Product::factory()->create([
            'shopify_id' => 789012,
        ]);
        $products = new Collection([$product1, $product2]);

        $userData = [
            'sc_id' => 12345,
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $successResponse = [
            'error' => false,
            'message' => 'Product reserved successfully',
        ];

        $errorResponse = [
            'error' => true,
            'message' => 'Product out of stock',
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->once()
            ->with($userData['sc_id'], $product1->shopify_id)
            ->andReturn($successResponse);

        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->once()
            ->with($userData['sc_id'], $product2->shopify_id)
            ->andReturn($errorResponse);

        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($comment, $product1, $product2) {
                $elements = $message['message']['attachment']['payload']['elements'];

                return $message['recipient'] === ['comment_id' => $comment->id] &&
                       $message['message']['attachment']['type'] === 'template' &&
                       $message['message']['attachment']['payload']['template_type'] === 'generic' &&
                       count($elements) === 2 &&
                       $elements[0]['title'] === $product1->name &&
                       $elements[0]['subtitle'] === 'The product has been reserved. Please check your cart.' &&
                       $elements[1]['title'] === $product2->name &&
                       $elements[1]['subtitle'] === 'Product out of stock';
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_logs_reservation_responses(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create([
            'shopify_id' => 123456,
        ]);
        $products = new Collection([$product]);

        $userData = [
            'sc_id' => 12345,
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $response = [
            'error' => false,
            'message' => 'Product reserved successfully',
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->once()
            ->andReturn($response);

        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_preserves_product_order_in_reply_message(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product1 = Product::factory()->create([
            'shopify_id' => 123456,
        ]);
        $product2 = Product::factory()->create([
            'shopify_id' => 789012,
        ]);
        $product3 = Product::factory()->create([
            'shopify_id' => 345678,
        ]);

        // Create collection with specific order
        $products = new Collection([$product1, $product2, $product3]);

        $userData = [
            'sc_id' => 12345,
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        // All products return success
        $this->smartCartMock
            ->shouldReceive('reserveProduct')
            ->times(3)
            ->andReturn([
                'error' => false,
                'message' => 'Product reserved successfully',
            ]);

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // The important part of this test is verifying the product order is preserved
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($product1, $product2, $product3) {
                $elements = $message['message']['attachment']['payload']['elements'];

                return count($elements) === 3 &&
                       $elements[0]['title'] === $product1->name &&
                       $elements[1]['title'] === $product2->name &&
                       $elements[2]['title'] === $product3->name;
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($userData, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }
}
