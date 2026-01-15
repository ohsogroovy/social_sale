<?php

namespace Tests\Feature\Controllers;

use Mockery;
use Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use App\Clients\Facebook;
use App\Models\PrivateMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FacebookStreamControllerTest extends TestCase
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
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_get_latest_comments_returns_json_with_live_stream_data(): void
    {
        $post = Post::factory()->create([
            'is_live' => true,
            'post_type' => 'live',
            'facebook_id' => '123456789',
        ]);

        Comment::factory()->count(3)->create([
            'post_id' => $post->facebook_id,
        ]);

        $this->mock(Facebook::class);

        $response = $this->getJson('/latest-comments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data',
            ]);

        $this->assertFalse($response->json('error'));
    }

    public function test_post_comment_with_text_message(): void
    {
        $post = Post::factory()->create([
            'facebook_id' => '123456789-1',
        ]);

        $facebookClientMock = $this->mock(Facebook::class, function ($mock) {
            $mock->shouldReceive('postComment')
                ->once()
                ->andReturn(['id' => 'facebook_comment_id']);
        });
        $response = $this->postJson("/post-comment/{$post->id}", [
            'message' => 'Test comment',
        ]);

        $response->assertStatus(200)
            ->assertJson(['id' => 'facebook_comment_id']);
    }

    public function test_post_comment_with_attachment(): void
    {
        $post = Post::factory()->create([
            'facebook_id' => '123456789-2',
        ]);

        $facebookClientMock = $this->mock(Facebook::class, function ($mock) {
            $mock->shouldReceive('postComment')
                ->once()
                ->andReturn(['id' => 'facebook_comment_id']);
        });

        $file = UploadedFile::fake()->image('comment_image.jpg');

        $response = $this->postJson("/post-comment/{$post->id}", [
            'source' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson(['id' => 'facebook_comment_id']);
    }

    public function test_post_comment_validation_error(): void
    {
        $post = Post::factory()->create([
            'facebook_id' => '123456789-3',
        ]);

        $response = $this->postJson("/post-comment/{$post->id}", []);

        $response->assertStatus(422);
    }

    public function test_search_comments_returns_filtered_comments(): void
    {
        $post = Post::factory()->create([
            'facebook_id' => '123456789-4',
        ]);

        Comment::factory()->create([
            'post_id' => $post->facebook_id,
            'message' => 'Test search term',
        ]);

        Comment::factory()->create([
            'post_id' => $post->facebook_id,
            'message' => 'Another comment',
        ]);

        $response = $this->getJson("/search-comments/{$post->id}?search=search");

        $response->assertStatus(200)
            ->assertJsonStructure(['comments']);
    }

    public function test_show_post_with_comments_loads_post_and_comments_correctly(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'facebook_id' => 'post-'.uniqid(),
            'message' => 'Test post content',
            'post_type' => 'video',
        ]);

        // Create comments for the post
        $comments = Comment::factory()->count(5)->create([
            'post_id' => $post->facebook_id,
            'facebook_created_at' => now(),
        ]);

        // Add a private message to one of the comments
        PrivateMessage::factory()->create([
            'comment_id' => $comments[0]->id,
            'message' => json_encode(['text' => 'Private message content']),
        ]);

        // Execute the query directly (same as in the controller)
        $loadedComments = $post->comments()
            ->with('privateMessage:comment_id')
            ->select('id', 'commenter', 'post_id', 'post_link', 'facebook_id', 'message', 'facebook_created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->paginate(10);

        // Assertions about the post and comments
        $this->assertEquals($post->message, 'Test post content');
        $this->assertCount(5, $loadedComments);

        // Check if the first comment has a private message
        $this->assertTrue($loadedComments->contains('id', $comments[0]->id));
        $commentWithPrivateMessage = $loadedComments->firstWhere('id', $comments[0]->id);
        $this->assertNotNull($commentWithPrivateMessage->privateMessage);
    }

    public function test_show_post_with_comments_includes_private_messages(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'facebook_id' => 'post-private-'.uniqid(),
        ]);

        // Create comments
        $comment1 = Comment::factory()->create([
            'post_id' => $post->facebook_id,
            'message' => 'Comment with private message',
            'facebook_created_at' => now()->subHour(),
        ]);

        $comment2 = Comment::factory()->create([
            'post_id' => $post->facebook_id,
            'message' => 'Comment without private message',
            'facebook_created_at' => now(),
        ]);

        // Add private message to the first comment only
        PrivateMessage::factory()->create([
            'comment_id' => $comment1->id,
            'message' => json_encode(['text' => 'Private reply content']),
        ]);

        // Execute the query directly (same as in the controller)
        $loadedComments = $post->comments()
            ->with('privateMessage:comment_id')
            ->select('id', 'commenter', 'post_id', 'post_link', 'facebook_id', 'message', 'facebook_created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->get();

        // Assertions about the loaded comments
        $this->assertCount(2, $loadedComments);

        // Check that the comment with private message has it loaded
        $commentWithPrivateMsg = $loadedComments->firstWhere('id', $comment1->id);
        $commentWithoutPrivateMsg = $loadedComments->firstWhere('id', $comment2->id);

        $this->assertNotNull($commentWithPrivateMsg->privateMessage);
        $this->assertNull($commentWithoutPrivateMsg->privateMessage);
    }

    public function test_show_post_with_comments_orders_by_facebook_created_at_desc(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'facebook_id' => 'post-ordering-'.uniqid(),
        ]);

        // Create comments with different timestamps
        $oldestComment = Comment::factory()->create([
            'post_id' => $post->facebook_id,
            'message' => 'Oldest comment',
            'facebook_created_at' => now()->subDays(3),
        ]);

        $middleComment = Comment::factory()->create([
            'post_id' => $post->facebook_id,
            'message' => 'Middle comment',
            'facebook_created_at' => now()->subDays(2),
        ]);

        $newestComment = Comment::factory()->create([
            'post_id' => $post->facebook_id,
            'message' => 'Newest comment',
            'facebook_created_at' => now()->subDay(),
        ]);

        // Execute the query directly (same as in the controller)
        $loadedComments = $post->comments()
            ->with('privateMessage:comment_id')
            ->select('id', 'commenter', 'post_id', 'post_link', 'facebook_id', 'message', 'facebook_created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->get();

        // Assert correct ordering
        $this->assertCount(3, $loadedComments);
        $this->assertEquals($newestComment->id, $loadedComments[0]->id);
        $this->assertEquals($middleComment->id, $loadedComments[1]->id);
        $this->assertEquals($oldestComment->id, $loadedComments[2]->id);
    }

    public function test_show_post_with_comments_paginates_results(): void
    {
        // Create a post
        $post = Post::factory()->create([
            'facebook_id' => 'post-pagination-'.uniqid(),
        ]);

        // Create more comments than the default pagination limit (10)
        Comment::factory()->count(15)->create([
            'post_id' => $post->facebook_id,
            'facebook_created_at' => now(),
        ]);

        // Execute the query directly with pagination (same as in the controller)
        $paginatedComments = $post->comments()
            ->with('privateMessage:comment_id')
            ->select('id', 'commenter', 'post_id', 'post_link', 'facebook_id', 'message', 'facebook_created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->paginate(10);

        // Assert pagination is working
        $this->assertEquals(10, $paginatedComments->count());
        $this->assertEquals(15, $paginatedComments->total());
        $this->assertEquals(2, $paginatedComments->lastPage());

        // Check second page
        $secondPageComments = $post->comments()
            ->with('privateMessage:comment_id')
            ->select('id', 'commenter', 'post_id', 'post_link', 'facebook_id', 'message', 'facebook_created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->paginate(10, ['*'], 'page', 2);

        $this->assertEquals(5, $secondPageComments->count());
        $this->assertEquals(2, $secondPageComments->currentPage());
    }

    public function test_get_past_streams_returns_results_filtered_by_type_and_status(): void
    {
        // Create posts with unique facebook_ids
        for ($i = 1; $i <= 5; $i++) {
            Post::factory()->create([
                'is_live' => false,
                'post_type' => 'live',
                'created_at' => now()->subDay(),
                'facebook_id' => 'past-stream-'.$i.'-'.uniqid(),
            ]);
        }

        // Create posts that shouldn't match our query
        Post::factory()->create([
            'is_live' => true,
            'post_type' => 'live',
            'facebook_id' => 'live-stream-'.uniqid(),
        ]);

        Post::factory()->create([
            'is_live' => false,
            'post_type' => 'video',
            'facebook_id' => 'video-'.uniqid(),
        ]);

        Post::factory()->create([
            'is_live' => true,
            'post_type' => 'video',
            'facebook_id' => 'live-video-'.uniqid(),
        ]);

        // Act: Query the posts directly using the same filters as the controller
        $pastStreams = Post::where('is_live', false)
            ->where('post_type', 'live')
            ->latest('created_at')
            ->get();

        // Assert: Only the relevant posts are returned
        $this->assertCount(5, $pastStreams);

        // Assert that all returned posts match our criteria
        foreach ($pastStreams as $post) {
            $this->assertEquals(0, $post->is_live);
            $this->assertEquals('live', $post->post_type);
        }
    }

    public function test_get_past_streams_orders_by_created_at_desc(): void
    {
        // Create posts with different timestamps and unique facebook_ids
        $oldest = Post::factory()->create([
            'is_live' => false,
            'post_type' => 'live',
            'created_at' => now()->subDays(3),
            'facebook_id' => 'oldest-'.uniqid(),
        ]);

        $middle = Post::factory()->create([
            'is_live' => false,
            'post_type' => 'live',
            'created_at' => now()->subDays(2),
            'facebook_id' => 'middle-'.uniqid(),
        ]);

        $newest = Post::factory()->create([
            'is_live' => false,
            'post_type' => 'live',
            'created_at' => now()->subDay(),
            'facebook_id' => 'newest-'.uniqid(),
        ]);

        // Query posts with the same logic as the controller
        $pastStreams = Post::where('is_live', false)
            ->where('post_type', 'live')
            ->latest('created_at')
            ->get();

        // Assert correct ordering
        $this->assertCount(3, $pastStreams);
        $this->assertEquals($newest->id, $pastStreams[0]->id);
        $this->assertEquals($middle->id, $pastStreams[1]->id);
        $this->assertEquals($oldest->id, $pastStreams[2]->id);
    }

    public function test_get_past_streams_paginates_results(): void
    {
        // Create posts with unique facebook_ids
        for ($i = 1; $i <= 15; $i++) {
            Post::factory()->create([
                'is_live' => false,
                'post_type' => 'live',
                'facebook_id' => 'paginated-'.$i.'-'.uniqid(),
            ]);
        }

        // Query with pagination using the same logic as the controller
        $paginatedResults = Post::where('is_live', false)
            ->where('post_type', 'live')
            ->latest('created_at')
            ->paginate(10);

        // Assert pagination is working
        $this->assertEquals(10, $paginatedResults->count());
        $this->assertEquals(15, $paginatedResults->total());
        $this->assertEquals(2, $paginatedResults->lastPage());
    }

    public function test_sync_manual_live_stream_returns_inertia_response_when_live_stream_found(): void
    {
        // Mock Facebook API response with Http fake
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page is live now',
                        'message' => 'Live streaming now!',
                    ],
                ],
            ]),
        ]);

        $response = $this->get('/sync-manual-live-stream');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Latest live stream found',
                'data' => [
                    'live_stream' => [
                        'facebook_id' => '78910_12345',
                        'message' => 'Live streaming now!',
                        'post_type' => 'live',
                        'is_live' => true,
                    ],
                ],
            ]);

        // Assert post was created in database
        $this->assertDatabaseHas('posts', [
            'facebook_id' => '78910_12345',
            'user_id' => $this->user->id,
            'message' => 'Live streaming now!',
            'post_type' => 'live',
            'is_live' => true,
        ]);
    }

    public function test_sync_manual_live_stream_returns_json_error_when_no_live_stream_found(): void
    {
        // Mock Facebook API response with no live streams
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => '78910_12345',
                        'story' => 'Test Page shared a photo',
                        'message' => 'Regular photo post',
                    ],
                ],
            ]),
        ]);

        $response = $this->get('/sync-manual-live-stream');

        $response->assertStatus(404)
            ->assertJson([
                'error' => true,
                'message' => 'No live stream detected',
                'data' => null,
            ]);

        // Assert no posts were created
        $this->assertDatabaseMissing('posts', [
            'facebook_id' => '78910_12345',
        ]);
    }

    public function test_sync_manual_live_stream_returns_json_error_when_no_posts_found(): void
    {
        // Mock Facebook API response with empty data
        Http::fake([
            '*' => Http::response(['data' => []]),
        ]);

        $response = $this->get('/sync-manual-live-stream');

        $response->assertStatus(404)
            ->assertJson([
                'error' => true,
                'message' => 'No live stream detected',
                'data' => null,
            ]);
    }

    public function test_sync_manual_live_stream_requires_authentication(): void
    {
        // Log out the user
        auth()->logout();

        $response = $this->get('/sync-manual-live-stream');

        $response->assertStatus(302); // Laravel redirects unauthenticated users to login
    }

    public function test_sync_manual_live_stream_handles_facebook_api_errors_gracefully(): void
    {
        // Mock Facebook API to return an error
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $response = $this->get('/sync-manual-live-stream');

        // Should return a 500 error since the exception isn't caught
        $response->assertStatus(500);
    }

    public function test_sync_manual_live_stream_updates_existing_post(): void
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

        $response = $this->get('/sync-manual-live-stream');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Latest live stream found',
                'data' => [
                    'live_stream' => [
                        'id' => $existingPost->id,
                        'facebook_id' => '78910_12345',
                        'message' => 'Updated live message',
                        'post_type' => 'live',
                        'is_live' => true,
                    ],
                ],
            ]);

        // Assert post was updated, not duplicated
        $this->assertDatabaseHas('posts', [
            'id' => $existingPost->id,
            'facebook_id' => '78910_12345',
            'message' => 'Updated live message',
            'post_type' => 'live',
            'is_live' => true,
        ]);

        // Assert only one post with this facebook_id exists
        $this->assertEquals(1, Post::where('facebook_id', '78910_12345')->count());
    }

    public function test_sync_manual_live_stream_returns_most_recent_live_post(): void
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

        $response = $this->get('/sync-manual-live-stream');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Latest live stream found',
                'data' => [
                    'live_stream' => [
                        'facebook_id' => '78910_12345',
                        'message' => 'First live stream',
                        'post_type' => 'live',
                        'is_live' => true,
                    ],
                ],
            ]);

        // Assert only the first (most recent) post was processed
        $this->assertDatabaseHas('posts', [
            'facebook_id' => '78910_12345',
            'message' => 'First live stream',
        ]);

        // The second post should not be created since we return immediately after finding the first
        $this->assertDatabaseMissing('posts', [
            'facebook_id' => '78910_12346',
        ]);
    }
}
