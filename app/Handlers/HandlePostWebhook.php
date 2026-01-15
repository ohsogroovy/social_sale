<?php

namespace App\Handlers;

use App\Models\User;
use App\Actions\ProcessFacebookPost;

class HandlePostWebhook
{
    public function __construct(protected ProcessFacebookPost $processFacebookPost) {}

    public function execute(array $change): void
    {
        \info('Handling post webhook', ['change' => $change]);
        $postId = $change['value']['post_id'];
        $pageId = (int) explode('_', $postId)[0];
        $itemType = $change['value']['item'];
        $message = $change['value']['message'] ?? null;

        $user = User::where('facebook_page_id', $pageId)->first();

        if ($itemType === 'status' && $user) {
            $this->processFacebookPost->execute($postId, $user, $itemType, $message);
        }
    }
}
