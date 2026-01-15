<?php

namespace Tests\Feature\Handlers;

use Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use App\Events\LiveStreamUpdated;
use App\Handlers\HandlePostWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HandlePostWebhookTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * A basic feature test example.
     */
    public function testHandlePostWebhook(): void
    {
        Event::fake();
        User::factory()->create(['facebook_page_id' => 123]);

        $change = [
            'value' => [
                'post_id' => '123_12345',
                'item' => 'status',
                'message' => 'Sample message',
            ],
        ];

        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '123_12345',
                        'story' => 'This live stream is live now',
                    ],
                    [
                        'id' => '123_67890',
                        'story' => 'This live stream was live',
                    ],
                    [
                        'id' => '123_11111',
                        'story' => 'This live stream was live',
                    ],
                    [
                        'id' => '123_22222',
                        'story' => 'This live stream was live',
                    ],
                    [
                        'id' => '123_33333',
                        'story' => 'This live stream was live',
                    ],
                ],
            ]),
        ]);

        /** @var \App\Handlers\HandlePostWebhook $handler */
        $handler = app(HandlePostWebhook::class);
        $handler->execute($change);

        $this->assertDatabaseHas(
            'posts',
            [
                'facebook_id' => '123_12345',
                'post_type' => 'live',
                'is_live' => true,
            ]
        );
        $post = Post::where('facebook_id', '123_12345')->first();
        Event::assertDispatched(LiveStreamUpdated::class, function ($event) use ($post) {
            return $event->post->facebook_id === $post->facebook_id;
        });
    }
}
