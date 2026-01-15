<?php

namespace Tests\Feature\Actions;

use Tests\TestCase;
use App\Models\Post;
use App\Events\LiveStreamUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use App\Actions\UpdateLiveStreamStatus;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UpdateLiveStreamStatusTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * A basic feature test example.
     */
    public function testUpdateLiveStreamStatus(): void
    {
        Event::fake();

        $post = Post::factory()->create([
            'is_live' => true,
            'post_type' => 'live',
        ]);

        Http::fake([
            '/*' => Http::response([
                'story' => 'This live stream was live',
            ]),
        ]);

        $updateLiveStreamStatus = app(UpdateLiveStreamStatus::class);
        $updateLiveStreamStatus->execute();
        $post->refresh();
        $this->assertDatabaseHas(Post::class, [
            'is_live' => false,
        ]);

        Event::assertDispatched(LiveStreamUpdated::class, function ($event) use ($post) {
            return $event->post->facebook_id === $post->facebook_id;
        });
    }
}
