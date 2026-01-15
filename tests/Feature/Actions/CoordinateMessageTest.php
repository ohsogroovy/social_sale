<?php

namespace Tests\Feature\Actions;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Comment;
use App\Models\Product;
use App\Models\PrivateMessage;
use App\Actions\CoordinateMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CoordinateMessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function testSendMessageIfAlreadySentMessage(): void
    {
        User::factory()->create();
        $comment = Comment::factory()->has(PrivateMessage::factory())->create();

        $sendMessageAction = \app(CoordinateMessage::class);
        $sendMessageAction->execute($comment);

        $this->assertDatabaseCount(PrivateMessage::class, 1);
    }

    public function testSendMessageIfMessageIsFromPageItself(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory(['facebook_id' => "{$user->facebook_page_id}_{$this->faker()->uuid()}"])->create();

        $sendMessageAction = \app(CoordinateMessage::class);
        $sendMessageAction->execute($comment);

        $this->assertDatabaseCount(PrivateMessage::class, 0);
    }

    public function testSendMessageIfMessageIsReplyToPageMessage(): void
    {
        User::factory()->create();
        $products = Product::factory()->count(5)->has(Tag::factory()->count($this->faker()->randomDigitNotZero()))->create();
        $targetProduct = $products->random()->load('tags');
        $pageComment = Comment::factory()->create(['message' => $this->faker()->text().$targetProduct->tags->random()->name]);
        $comment = Comment::factory(['parent_id' => $pageComment->facebook_id, 'is_from_page' => false])->create();

        $fakeFacebookSendMessageRes = ['recipient_id' => $this->faker()->randomNumber(4), 'message_id' => $this->faker()->randomNumber(4)];
        Http::fake([
            'graph.facebook.com/*' => Http::response($fakeFacebookSendMessageRes),
        ]);

        $sendMessageAction = \app(CoordinateMessage::class);
        $sendMessageAction->execute($comment);

        $this->assertDatabaseCount(PrivateMessage::class, 1);
        $this->assertDatabaseHas(PrivateMessage::class, ['message_id' => $fakeFacebookSendMessageRes['message_id']]);
    }

    public function testSendIndependentMessageWhichDoesContainTriggerWord(): void
    {
        User::factory()->create();
        $products = Product::factory()->count(5)->has(Tag::factory()->count($this->faker()->randomDigitNotZero()))->create();
        $targetProduct = $products->random()->load('tags');
        $comment = Comment::factory()->create(['message' => $this->faker()->text().$targetProduct->tags->random()->name, 'is_from_page' => false]);

        $fakeFacebookSendMessageRes = ['recipient_id' => $this->faker()->randomNumber(4), 'message_id' => $this->faker()->randomNumber(4)];
        Http::fake([
            'graph.facebook.com/*' => Http::response($fakeFacebookSendMessageRes),
        ]);

        $sendMessageAction = \app(CoordinateMessage::class);
        $sendMessageAction->execute($comment);

        $this->assertDatabaseCount(PrivateMessage::class, 1);
        $this->assertDatabaseHas(PrivateMessage::class, ['message_id' => $fakeFacebookSendMessageRes['message_id']]);
    }
}
