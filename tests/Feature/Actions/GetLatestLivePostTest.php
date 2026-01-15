<?php

namespace Tests\Feature\Actions;

use Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use App\Actions\GetLatestLivePost;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetLatestLivePostTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'facebook_user_id' => 123456,
            'facebook_page_id' => 78910,
            'facebook_user_token' => 'test_user_token',
            'facebook_page_token' => 'test_page_token',
        ]);
    }

    public function test_it_finds_currently_live_post(): void
    {
        // Mock Facebook API response with a currently live post
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page is live now',
                        'message' => 'Live streaming now!',
                    ],
                    [
                        'id' => '78910_12346',
                        'story' => 'Regular post',
                        'message' => 'Just a regular post',
                    ],
                ],
            ]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Assert successful result
        $this->assertTrue($result['success']);
        $this->assertEquals('Live stream detected', $result['message']);
        $this->assertArrayHasKey('post', $result['data']);
        $this->assertArrayHasKey('facebook_data', $result['data']);

        // Assert post data
        $post = $result['data']['post'];
        $this->assertEquals('78910_12345', $post->facebook_id);
        $this->assertEquals('Live streaming now!', $post->message);
        $this->assertEquals('live', $post->post_type);
        $this->assertTrue($post->is_live);
        $this->assertEquals($this->user->id, $post->user_id);

        // Assert Facebook data
        $facebookData = $result['data']['facebook_data'];
        $this->assertEquals('78910_12345', $facebookData['id']);
        $this->assertEquals('Test Page is live now', $facebookData['story']);
        $this->assertEquals('Live streaming now!', $facebookData['message']);

        // Assert post was created in database
        $this->assertDatabaseHas('posts', [
            'facebook_id' => '78910_12345',
            'user_id' => $this->user->id,
            'message' => 'Live streaming now!',
            'post_type' => 'live',
            'is_live' => true,
        ]);
    }

    public function test_it_returns_first_live_post_when_multiple_found(): void
    {
        // Mock Facebook API response with multiple live posts
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page is live now',
                        'message' => 'First live stream',
                    ],
                    [
                        'id' => '78910_12346',
                        'story' => 'Test Page is live now',
                        'message' => 'Second live stream',
                    ],
                ],
            ]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Should return the first (most recent) live post
        $this->assertTrue($result['success']);
        $post = $result['data']['post'];
        $this->assertEquals('78910_12345', $post->facebook_id);
        $this->assertEquals('First live stream', $post->message);
    }

    public function test_it_handles_no_posts_found(): void
    {
        // Mock Facebook API response with no posts
        Http::fake([
            '*' => Http::response(['data' => []]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Assert failure result
        $this->assertFalse($result['success']);
        $this->assertEquals('No posts found', $result['message']);
        $this->assertNull($result['data']);
    }

    public function test_it_handles_no_live_posts_found(): void
    {
        // Mock Facebook API response with regular posts only
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page shared a photo',
                        'message' => 'Regular photo post',
                    ],
                    [
                        'id' => '78910_12346',
                        'story' => 'Test Page shared a video',
                        'message' => 'Regular video post',
                    ],
                ],
            ]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Assert failure result
        $this->assertFalse($result['success']);
        $this->assertEquals('No live stream detected', $result['message']);
        $this->assertNull($result['data']);

        // Assert no posts were created in database
        $this->assertDatabaseMissing('posts', [
            'facebook_id' => '78910_12345',
        ]);
        $this->assertDatabaseMissing('posts', [
            'facebook_id' => '78910_12346',
        ]);
    }

    public function test_it_ignores_was_live_posts(): void
    {
        // Mock Facebook API response with "was live" posts only
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page was live',
                        'message' => 'Previous live stream',
                    ],
                    [
                        'id' => '78910_12346',
                        'story' => 'Test Page shared a photo',
                        'message' => 'Regular post',
                    ],
                ],
            ]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Should not find any currently live posts
        $this->assertFalse($result['success']);
        $this->assertEquals('No live stream detected', $result['message']);
        $this->assertNull($result['data']);
    }

    public function test_it_updates_existing_post(): void
    {
        // Create existing post in database
        $existingPost = Post::factory()->create([
            'facebook_id' => '78910_12345',
            'user_id' => $this->user->id,
            'message' => 'Old message',
            'post_type' => 'photo',
            'is_live' => false,
        ]);

        // Mock Facebook API response
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page is live now',
                        'message' => 'Updated live message',
                    ],
                ],
            ]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Assert post was updated
        $this->assertTrue($result['success']);

        $post = $result['data']['post'];
        $this->assertEquals($existingPost->id, $post->id);
        $this->assertEquals('Updated live message', $post->message);
        $this->assertEquals('live', $post->post_type);
        $this->assertTrue($post->is_live);

        // Assert database was updated
        $this->assertDatabaseHas('posts', [
            'id' => $existingPost->id,
            'facebook_id' => '78910_12345',
            'message' => 'Updated live message',
            'post_type' => 'live',
            'is_live' => true,
        ]);
    }

    public function test_it_handles_posts_without_story(): void
    {
        // Mock Facebook API response with posts without story field
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'message' => 'Post without story field',
                    ],
                    [
                        'id' => '78910_12346',
                        'story' => 'Test Page is live now',
                        'message' => 'Post with live story',
                    ],
                ],
            ]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Should find the second post with live story
        $this->assertTrue($result['success']);
        $post = $result['data']['post'];
        $this->assertEquals('78910_12346', $post->facebook_id);
        $this->assertEquals('Post with live story', $post->message);
    }

    public function test_it_handles_posts_without_message(): void
    {
        // Mock Facebook API response with post without message field
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page is live now',
                        // No message field
                    ],
                ],
            ]),
        ]);

        $action = app(GetLatestLivePost::class);
        $result = $action->execute($this->user);

        // Should handle missing message gracefully
        $this->assertTrue($result['success']);
        $post = $result['data']['post'];
        $this->assertEquals('78910_12345', $post->facebook_id);
        $this->assertEquals('', $post->message);
    }
}
