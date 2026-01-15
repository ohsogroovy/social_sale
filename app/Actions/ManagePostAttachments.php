<?php

namespace App\Actions;

use App\Models\Post;
use App\Models\User;
use App\Clients\Facebook;

class ManagePostAttachments
{
    public function __construct(protected Facebook $facebookClient) {}

    public function execute(string $postId, User $user): void
    {
        $postAttachments = $this->facebookClient->getPostAttachments($postId);

        if (empty($postAttachments)) {
            \info('No attachments found for post', ['post_id' => $postId]);

            return;
        }
        $subAttachments = $postAttachments[0]['subattachments']['data'] ?? [];

        foreach ($subAttachments as $attachment) {
            $description = $attachment['description'] ?? null;
            $postType = $attachment['type'];
            $pageId = explode('_', $postId)[0];
            $attachmentPostId = $pageId.'_'.$attachment['target']['id'];

            Post::updateOrCreate(
                ['facebook_id' => $attachmentPostId, 'user_id' => $user->id],
                [
                    'message' => $description,
                    'post_type' => $postType,
                    'is_live' => false,
                ]
            );
        }
        \info('Post attachments saved for the post', ['post_id' => $postId]);
    }
}
