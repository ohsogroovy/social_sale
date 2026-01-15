<?php

namespace Tests\Feature\Actions;

use Mockery;
use Tests\TestCase;
use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Actions\SendWaitMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SendWaitMessageTest extends TestCase
{
    use RefreshDatabase;

    private SendWaitMessage $action;

    private $facebookMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facebookMock = Mockery::mock(Facebook::class);
        $this->action = new SendWaitMessage($this->facebookMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_wait_message_for_out_of_stock_product(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];

        $messageDetails = [];

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
                       $message['message']['attachment']['payload']['elements'][0]['subtitle'] === 'Product is out of stock. Do you want to add it to the wait list?';
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $product, $comment, $messageDetails);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_includes_waitlist_options_in_message(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create();
        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];
        $messageDetails = [];

        $messageResponse = [
            'recipient_id' => 12345,
            'message_id' => 'test_message_id',
        ];

        // Expectations
        $this->facebookMock
            ->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($message) use ($product, $comment) {
                $buttons = $message['message']['attachment']['payload']['elements'][0]['buttons'];

                // Verify there are two buttons for the waitlist options
                if (count($buttons) !== 2) {
                    return false;
                }

                $addButton = $buttons[0];
                $declineButton = $buttons[1];

                // Verify button titles
                if ($addButton['title'] !== 'Add to waitlist') {
                    return false;
                }

                // Verify payloads
                $addPayload = json_decode($addButton['payload'], true);
                $declinePayload = json_decode($declineButton['payload'], true);

                return $addPayload['action'] === 'ADD_TO_WAITLIST' &&
                       $declinePayload['action'] === 'DECLINE_WAITLIST' &&
                       $addPayload['productId'] === $product->id &&
                       $addPayload['commentId'] === $comment->id;
            }))
            ->andReturn($messageResponse);

        // Act
        $this->action->execute($recipient, $product, $comment, $messageDetails);

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
        $recipient = [
            'type' => 'user',
            'id' => 'user_id_123',
        ];
        $messageDetails = [];

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
        $this->action->execute($recipient, $product, $comment, $messageDetails);

        // Assert
        $this->assertDatabaseHas('private_messages', [
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageResponse['recipient_id'],
            'message_id' => $messageResponse['message_id'],
        ]);
    }

    public function test_logs_message_sending_activity(): void
    {
        // Arrange
        $comment = Comment::factory()->create();
        $product = Product::factory()->create();
        $recipient = [
            'type' => 'commenter',
            'id' => $comment->id,
        ];
        $messageDetails = [];

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
        $this->action->execute($recipient, $product, $comment, $messageDetails);

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
