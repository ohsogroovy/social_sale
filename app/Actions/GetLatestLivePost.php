<?php

namespace App\Actions;

use App\Models\Post;
use App\Models\User;
use App\Clients\Facebook;

class GetLatestLivePost
{
    public function __construct(
        protected Facebook $facebookClient
    ) {}

    public function execute(User $user): array
    {
        $limit = 5;
        $publishedPosts = $this->facebookClient->getPagePublishedPosts($limit);

        if (empty($publishedPosts['data'])) {
            return [
                'success' => false,
                'message' => 'No posts found',
                'data' => null,
            ];
        }

        foreach ($publishedPosts['data'] as $postData) {
            $postId = $postData['id'];
            $story = $postData['story'] ?? '';

            $isCurrentlyLive = isset($postData['story']) && str_contains($story, 'is live now');

            if ($isCurrentlyLive) {
                $post = Post::updateOrCreate(
                    ['facebook_id' => $postId, 'user_id' => $user->id],
                    [
                        'facebook_id' => $postId,
                        'message' => $postData['message'] ?? '',
                        'post_type' => 'live',
                        'is_live' => true,
                    ]
                );

                // Load comments for the post
                $post->load(['comments' => fn ($comments) => $comments->orderBy('facebook_created_at', 'desc')]);

                return [
                    'success' => true,
                    'message' => 'Live stream detected',
                    'data' => [
                        'post' => $post,
                        'facebook_data' => $postData,
                    ],
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'No live stream detected',
            'data' => null,
        ];
    }
}
