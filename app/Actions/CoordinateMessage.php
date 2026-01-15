<?php

namespace App\Actions;

use App\Models\Comment;
use App\Clients\Facebook;
use App\Clients\SmartCart;

class CoordinateMessage
{
    public function __construct(private SmartCart $smartCartClient, private Facebook $facebook) {}

    public function execute(Comment $comment): void
    {
        \logger()->info('Sending message to comment.', ['comment_id' => $comment->id]);

        if ($comment->privateMessage()->exists()) {
            \logger()->info('Private message already sent for this comment.', ['comment_id' => $comment->id]);

            return;
        }

        if ($comment->is_from_page) {
            \logger()->info('Comment is from the page itself, hence not sending DM.', ['comment_id' => $comment->id, 'post_id' => $comment->post_id]);

            return;
        }
        $referencesInParent = false;
        $referencedProducts = collect();

        if (($comment->messageContains('sold') || $comment->messageContains('reserve'))) {
            if ($comment->isReplyToPage()) {
                $referencesInParent = true;
            } else {
                $post = $comment->post;

                if (! $post) {
                    // TODO: Make this dynamic based on the database post. For now, we are fetching the post data from Facebook.
                    $postId = $comment->post_id;
                    $postDetails = $this->facebook->getPostData($postId);
                    $message = $postDetails['message'] ?? '';
                } else {
                    $message = $post->message;
                }
                // Check if the post message contains the tag or name of the product
                $referencedProducts = (new \App\Queries\ProductsInComment)($message)
                    ->limit(10)
                    ->get();
            }
        }

        if ($referencedProducts->isEmpty()) {
            $referencedProducts = (new \App\Queries\ProductsInComment)($referencesInParent ? $comment->parent() : $comment)
                ->limit(10)
                ->get();
        }

        if ($referencedProducts->isEmpty()) {
            \logger()->info('Comment does not contain any products reference.', ['comment_id' => $comment->id, 'post_id' => $comment->post_id]);

            return;
        }

        $product = $referencedProducts->first();
        $commenter = $comment->commenter;
        $customer = $this->smartCartClient->customer(['name' => $commenter]);
        $user = ['name' => $commenter, 'id' => $comment->facebook_id, 'type' => 'commenter'];

        if ($customer) {
            \info('The customer found while sending message', ['customer' => $customer]);
            if ($comment->messageContains('reserve')) {
                $user['sc_id'] = $customer['id'];
                \info('The customer found and the comment has reserve', ['comment' => $comment, 'product' => $product]);
                \app(ReserveProduct::class)->execute($user, $referencedProducts, $comment);
            } else {
                \info('The customer found and has not reserve keyword in comment. Sending message for reservation', ['comment' => $comment]);
                \app(PromptReserveProduct::class)->execute($user, $referencedProducts, $comment);
            }
        } else {
            \info('The customer not found while sending message', ['customer' => $customer]);
            if ($comment->messageContains('reserve')) {
                \info('The customer not found and the comment has reserve', ['comment' => $comment, 'product' => $product]);
                \app(PromptAuthorizationAndReserveProduct::class)->execute($user, $referencedProducts, $comment);
            } else {
                \info('The customer not found and has not reserve keyword in comment. Sending message for reservation', ['comment' => $comment]);
                \app(ProductMessage::class)->execute($user, $referencedProducts, $comment);
            }
        }
    }
}
