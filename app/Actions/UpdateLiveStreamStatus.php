<?php

namespace App\Actions;

use Exception;
use App\Models\Post;
use App\Clients\Facebook;
use App\Events\LiveStreamUpdated;

class UpdateLiveStreamStatus
{
    public function __construct(private Facebook $facebookClient) {}

    public function execute(): void
    {
        $livePosts = Post::where('is_live', true)->where('post_type', 'live')->get();
        foreach ($livePosts as $post) {
            try {
                $this->updateStatus($post);
            } catch (Exception $e) {
                $post->update(['is_live' => false]);
                \logger()->error('Failed to update post status', ['post_id' => $post->facebook_id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function updateStatus(Post $post): void
    {
        $postData = $this->facebookClient->getPostData($post->facebook_id);
        if (isset($postData['story']) && str_contains($postData['story'], 'was live')) {
            $post->update(['is_live' => false]);
            logger()->info("Updated post status to 'was live'", ['post_id' => $post->facebook_id]);
            LiveStreamUpdated::dispatch($post);
        }
    }
}
