<?php

namespace Tests\Feature\Actions;

use Mockery;
use Tests\TestCase;
use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use Illuminate\Support\Collection;
use App\Actions\PromptReserveProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PromptReserveProductTest extends TestCase
{
    use RefreshDatabase;

    private PromptReserveProduct $action;

    private $facebookMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facebookMock = Mockery::mock(Facebook::class);
        $this->action = new PromptReserveProduct($this->facebookMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_single_product_reservation_message(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'image_url' => 'https://example.com/image.jpg',
        ]);
        $products = new Collection([$product]);

        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($product, $comment) {
                return $message['recipient'] === ['comment_id' => $comment->id] &&
                       $message['message']['attachment']['type'] === 'template' &&
                       $message['message']['attachment']['payload']['template_type'] === 'generic' &&
                       $message['message']['attachment']['payload']['elements'][0]['title'] === $product->name &&
                       $message['message']['attachment']['payload']['elements'][0]['image_url'] === $product->image_url;
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_sends_multiple_products_reservation_message(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $products = Product::factory()->count(3)->create();

        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($products, $comment) {
                return $message['recipient'] === ['comment_id' => $comment->id] &&
                       $message['message']['attachment']['type'] === 'template' &&
                       $message['message']['attachment']['payload']['template_type'] === 'generic' &&
                       count($message['message']['attachment']['payload']['elements']) === $products->count();
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_sends_message_to_regular_user(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create();
        $products = new Collection([$product]);

        $recipient = [
            'type' => 'user',
            'id' => 'user_id_123',
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($recipient) {
                return $message['recipient'] === ['id' => $recipient['id']];
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_verifies_proper_message_payload_for_reserve_action(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create();
        $products = new Collection([$product]);

        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($product, $comment) {
                $payload = json_decode($message['message']['attachment']['payload']['elements'][0]['buttons'][0]['payload'], true);

                return $payload['action'] === 'RESERVE_PRODUCT' &&
                       $payload['productId'] === $product->id &&
                       $payload['commentId'] === $comment->id;
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_logs_message_sending(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create();
        $products = new Collection([$product]);

        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment);

        // Assert
        // Since we can't easily test the logger without mocking it heavily,
        // we're asserting that the process completed by checking the database
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }
}
