<?php

namespace Tests\Feature\Handlers;

use Tests\TestCase;
use App\Models\User;
use App\Models\Comment;
use App\Models\Product;
use App\Events\CommentPosted;
use App\Actions\CoordinateMessage;
use Illuminate\Support\Facades\Event;
use App\Handlers\HandleCommentsWebhook;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HandleCommentsWebhookTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * A basic feature test example.
     */
    public function testHandleNewComment(): void
    {
        Event::fake();
        $sendMessage = \Mockery::mock(CoordinateMessage::class);
        $sendMessage->shouldReceive('execute')->never();
        $this->app->instance(CoordinateMessage::class, $sendMessage);

        $change = [
            'value' => [
                'post_id' => '123_12345',
                'comment_id' => 'comment123',
                'from' => ['id' => 123, 'name' => 'User Name'],
                'message' => 'Test comment message',
                'parent_id' => '123_12345',
                'created_time' => now()->toDateTimeString(),
                'post' => ['permalink_url' => 'http://example.com'],
                'verb' => 'add',
            ],
            'field' => 'status',
        ];

        /** @var \App\Handlers\HandleCommentsWebhook $handler */
        $handler = app(HandleCommentsWebhook::class);
        $handler->execute($change);

        $this->assertDatabaseHas(
            'comments',
            [
                'facebook_id' => 'comment123',
                'post_id' => '123_12345',
                'parent_id' => '123_12345',
                'post_type' => 'status',
                'message' => 'Test comment message',
            ],
        );
        $comment = Comment::where('facebook_id', 'comment123')->first();

        Event::assertDispatched(CommentPosted::class, function ($event) use ($comment) {
            return $event->comment->facebook_id === $comment->facebook_id;
        });
    }

    public function testCommentFromPage(): void
    {
        Event::fake();

        $sendMessage = \Mockery::mock(CoordinateMessage::class);
        $sendMessage->shouldReceive('execute')->never();
        $this->app->instance(CoordinateMessage::class, $sendMessage);

        $change = [
            'value' => [
                'post_id' => '123_12345',
                'comment_id' => 'comment123',
                'from' => ['id' => 123, 'name' => 'Page Name'],
                'message' => 'Comment from the page',
                'parent_id' => '123_12345',
                'created_time' => now()->toDateTimeString(),
                'post' => ['permalink_url' => 'http://example.com'],
                'verb' => 'add',
            ],
            'field' => 'status',
        ];

        $handler = app(HandleCommentsWebhook::class);
        $handler->execute($change);

        $this->assertDatabaseHas(
            'comments',
            [
                'facebook_id' => 'comment123',
                'post_id' => '123_12345',
                'parent_id' => '123_12345',
                'post_type' => 'status',
                'message' => 'Comment from the page',
                'is_from_page' => true,
            ]
        );
        $comment = Comment::where('facebook_id', 'comment123')->first();

        Event::assertDispatched(CommentPosted::class, function ($event) use ($comment) {
            return $event->comment->facebook_id === $comment->facebook_id;
        });
    }

    public function testCommentReplyToPageWithSold(): void
    {
        Event::fake();

        User::factory()->create(['facebook_page_id' => 123]);
        $parentComment = Comment::factory()->create(['facebook_id' => '123_12345', 'post_id' => '123_12345', 'message' => 'Reply to this comment to purchase "Sparky Bracelet" "JWI-U82U-HUIU-WISD" Only 10+ left!']);
        $change = [
            'value' => [
                'post_id' => '123_12345',
                'comment_id' => 'comment123',
                'from' => ['id' => 456, 'name' => 'User Name'],
                'message' => 'I want to buy this product. Sold!',
                'created_time' => now()->toDateTimeString(),
                'parent_id' => $parentComment->facebook_id,
                'post' => ['permalink_url' => 'http://example.com'],
                'verb' => 'add',
            ],
            'field' => 'status',
        ];

        $handler = app(HandleCommentsWebhook::class);
        $handler->execute($change);

        $this->assertDatabaseHas(
            'comments',
            [
                'facebook_id' => 'comment123',
                'post_id' => '123_12345',
                'parent_id' => '123_12345',
                'post_type' => 'status',
                'message' => 'I want to buy this product. Sold!',
            ]
        );
        $comment = Comment::where('facebook_id', 'comment123')->first();

        Event::assertDispatched(CommentPosted::class, function ($event) use ($comment) {
            return $event->comment->facebook_id === $comment->facebook_id;
        });
    }

    public function testCommentReferencingProductAndReserve(): void
    {
        Event::fake();

        $product = Product::factory()->create();
        $product->tags()->create(['name' => 'Product Name']);

        $change = [
            'value' => [
                'post_id' => '123_12345',
                'comment_id' => 'comment123',
                'from' => ['id' => 456, 'name' => 'User Name'],
                'message' => 'I would like to reserve this Product Name',
                'parent_id' => '123_12345',
                'created_time' => now()->toDateTimeString(),
                'post' => ['permalink_url' => 'http://example.com'],
                'verb' => 'add',
            ],
            'field' => 'status',
        ];

        /** @var \App\Handlers\HandleCommentsWebhook $handler */
        $handler = app(HandleCommentsWebhook::class);
        $handler->execute($change);

        $this->assertDatabaseHas(
            'comments',
            [
                'facebook_id' => 'comment123',
                'post_id' => '123_12345',
                'parent_id' => '123_12345',
                'post_type' => 'status',
                'message' => 'I would like to reserve this Product Name',
            ]
        );
        Event::assertDispatched(CommentPosted::class);
    }

    public function testCommentWithNoProductReference(): void
    {
        Event::fake();

        $sendMessage = \Mockery::mock(CoordinateMessage::class);
        $sendMessage->shouldReceive('execute')->never();
        $this->app->instance(CoordinateMessage::class, $sendMessage);

        $change = [
            'value' => [
                'post_id' => '123_12345',
                'comment_id' => 'comment123',
                'parent_id' => '123_12345',
                'from' => ['id' => 456, 'name' => 'User Name'],
                'message' => 'This comment does not reference a product',
                'created_time' => now()->toDateTimeString(),
                'post' => ['permalink_url' => 'http://example.com'],
                'verb' => 'add',
            ],
            'field' => 'status',
        ];

        $handler = app(HandleCommentsWebhook::class);
        $handler->execute($change);

        $this->assertDatabaseHas(
            'comments',
            [
                'facebook_id' => 'comment123',
                'post_id' => '123_12345',
                'parent_id' => '123_12345',
                'post_type' => 'status',
                'message' => 'This comment does not reference a product',
            ]
        );
        $comment = Comment::where('facebook_id', 'comment123')->first();

        Event::assertDispatched(CommentPosted::class, function ($event) use ($comment) {
            return $event->comment->facebook_id === $comment->facebook_id;
        });
    }
}
