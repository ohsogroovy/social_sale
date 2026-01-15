<?php

namespace App\Handlers;

use App\Models\Comment;
use App\Events\CommentPosted;

class HandleCommentsWebhook
{
    public function execute(array $change): void
    {
        $postId = $change['value']['post_id'] ?? null;
        $parentId = $change['value']['parent_id'] ?? null;
        $commentId = $change['value']['comment_id'];
        $from = $change['value']['from'];
        $field = $change['field'];
        $verb = $change['value']['verb'];
        \logger()->info('Handling new comment ', ['comment_id' => $commentId, 'post_id' => $postId, 'post_type' => $field] + \compact('verb'));

        $pageId = (int) explode('_', $postId)[0];
        $isFromPage = $from['id'] == $pageId;

        if ($verb === 'remove') {
            /** @var Comment */
            $comment = Comment::where('facebook_id', $commentId)->first();
            if ($comment == null) {
                \logger()->info('Comment not found. ', ['comment_id' => $commentId]);

                return;
            }
            $comment->privateMessage?->delete();
            $comment->delete();
            \logger()->info('Comment deleted successfully. ', ['comment_id' => $commentId]);

            return;
        }

        try {
            /** @var Comment */
            $comment = Comment::updateOrCreate(
                ['facebook_id' => $commentId],
                [
                    'facebook_user_id' => $from['id'],
                    'commenter' => $from['name'],
                    'post_id' => $postId,
                    'parent_id' => $parentId,
                    'post_type' => $field,
                    'post_link' => $change['value']['post']['permalink_url'],
                    'message' => $change['value']['message'],
                    'facebook_created_at' => $change['value']['created_time'],
                    'is_from_page' => $isFromPage,
                ]);
        } catch (\Exception $e) {
            \logger()->debug($e->getMessage());
            \logger()->error('Error creating comment ', ['comment' => $change]);

            return;
        }

        CommentPosted::dispatch($comment);
    }
}
