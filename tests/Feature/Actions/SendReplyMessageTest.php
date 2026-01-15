<?php

namespace Tests\Feature\Actions;

use Mockery;
use Tests\TestCase;
use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Actions\SendReplyMessage;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SendReplyMessageTest extends TestCase
{
    use RefreshDatabase;

    private SendReplyMessage $action;

    private $facebookMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facebookMock = Mockery::mock(Facebook::class);
        $this->action = new SendReplyMessage($this->facebookMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_single_product_reply_message(): void
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

        $messageDetails = [
            $product->id => [
                'error' => false,
                'message' => 'Product has been reserved',
            ],
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
                       $message['message']['attachment']['payload']['elements'][0]['image_url'] === $product->image_url &&
                       $message['message']['attachment']['payload']['elements'][0]['subtitle'] === 'The product has been reserved. Please check your cart.';
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment, $messageDetails);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_sends_single_product_error_message(): void
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

        $messageDetails = [
            $product->id => [
                'error' => true,
                'message' => 'Product is out of stock',
            ],
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($product, $messageDetails) {
                return $message['message']['attachment']['payload']['elements'][0]['subtitle'] === $messageDetails[$product->id]['message'];
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment, $messageDetails);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_handles_missing_message_details(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create();
        $products = new Collection([$product]);

        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageDetails = []; // No details for this product

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) {
                return $message['message']['attachment']['payload']['elements'][0]['subtitle'] === 'Unable to determine product status.';
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment, $messageDetails);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_sends_multiple_products_reply_message(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $products = Product::factory()->count(2)->create();

        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageDetails = [
            $products[0]->id => [
                'error' => false,
                'message' => 'Product has been reserved',
            ],
            $products[1]->id => [
                'error' => true,
                'message' => 'Product is out of stock',
            ],
        ];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) {
                return count($message['message']['attachment']['payload']['elements']) === 2;
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $products, $comment, $messageDetails);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_sends_message_to_user_not_commenter(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create();
        $products = new Collection([$product]);

        $recipient = [
            'type' => 'user',
            'id' => 'user_id_123',
        ];

        $messageDetails = [
            $product->id => [
                'error' => false,
                'message' => 'Product has been reserved',
            ],
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
        $this->action->execute($recipient, $products, $comment, $messageDetails);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }
}
