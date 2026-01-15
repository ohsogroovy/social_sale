<?php

namespace App\Actions;

use App\Models\Post;
use App\Models\User;
use App\Clients\Facebook;
use App\Events\LiveStreamUpdated;

class ProcessFacebookPost
{
    public function __construct(
        protected Facebook $facebookClient,
        protected ManagePostAttachments $managePostAttachments
    ) {}

    public function execute(string $postId, User $user, string $itemType, ?string $message): void
    {
        $limit = 5;
        $publishedPosts = $this->facebookClient->getPagePublishedPosts($limit);

        foreach ($publishedPosts['data'] as $postData) {
            if ($postData['id'] == $postId) {
                logger()->info($postData['story'] ?? 'There is no story');
                $isLive = isset($postData['story']) && str_contains($postData['story'], 'is live now') ? true : false;
                $postType = $isLive ? 'live' : $itemType;

                $post = Post::updateOrCreate(
                    ['facebook_id' => $postId, 'user_id' => $user->id],
                    [
                        'facebook_id' => $postId,
                        'message' => $message,
                        'post_type' => $postType,
                        'is_live' => $isLive,
                    ]
                );

                \info('The post has been created', ['post' => $post]);
                $this->managePostAttachments->execute($postId, $user);

                if ($isLive) {
                    logger()->info('Live stream started', ['post_id' => $postId]);
                    LiveStreamUpdated::dispatch($post);
                }

                break;
            }
        }
    }
}
