<?php

namespace Tests\Feature\Actions;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Actions\ManagePostAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ManagePostAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function testItProcessesPostAttachments(): void
    {
        $user = User::factory()->create(['facebook_page_id' => 123]);

        // Fake Facebook API response for post attachments
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'subattachments' => [
                            'data' => [
                                [
                                    'description' => 'Attachment 1 description',
                                    'type' => 'photo',
                                    'target' => ['id' => '67890'],
                                ],
                                [
                                    'description' => 'Attachment 2 description',
                                    'type' => 'video',
                                    'target' => ['id' => '54321'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $action = app(ManagePostAttachments::class);
        $action->execute('123_12345', $user);

        $this->assertDatabaseHas('posts', [
            'facebook_id' => '123_67890',
            'message' => 'Attachment 1 description',
            'post_type' => 'photo',
            'is_live' => false,
        ]);

        $this->assertDatabaseHas('posts', [
            'facebook_id' => '123_54321',
            'message' => 'Attachment 2 description',
            'post_type' => 'video',
            'is_live' => false,
        ]);
    }

    public function testItHandlesNoAttachmentsGracefully(): void
    {
        $user = User::factory()->create(['facebook_page_id' => 123]);

        Http::fake([
            '*' => Http::response(['data' => []]),
        ]);
        $action = app(ManagePostAttachments::class);
        $action->execute('123_12345', $user);

        $this->assertDatabaseMissing('posts', ['facebook_id' => '123_67890']);
    }
}
