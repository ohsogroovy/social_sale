<?php

namespace App\Handlers\Facebook;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\SmartCart;
use App\Actions\AddToWaitList;
use App\Actions\ReserveProduct;

class MessageHandler
{
    public function __construct(private SmartCart $smartCartClient, private ReserveProduct $reserveProduct, private AddToWaitList $addToWaitList) {}

    public function handle(array $message): void
    {
        if (isset($message['postback'])) {
            $this->handlePostback($message['postback']);
        }
    }

    protected function handlePostback(array $postback): void
    {
        $buttonPayload = json_decode($postback['payload']);
        if (! isset($buttonPayload->action)) {
            \logger()->debug('Button payload does not contain action.', ['payload' => $buttonPayload]);

            return;
        }
        // FIXME: We should also handle NO_ACTION
        \info('Processing postback', ['payload' => $buttonPayload]);

        [$comment, $product, $user] = $this->fetchCommentProductAndUser($buttonPayload);

        if (! $comment || ! $product || ! $user) {
            \logger()->warning('Missing required data for postback handling.', ['payload' => $buttonPayload]);

            return;
        }
        \info('The action is', [$buttonPayload->action]);
        if ($buttonPayload->action === 'ADD_TO_WAITLIST') {
            \info('Adding to waitlist', ['user' => $user, 'product' => $product, 'comment' => $comment]);
            $this->addToWaitList->execute($user, $product, $comment);

            return;
        } elseif ($buttonPayload->action === 'RESERVE_PRODUCT') {
            \info('Reserving product', ['user' => $user, 'product' => $product, 'comment' => $comment]);
            $this->reserveProduct->execute($user, collect([$product]), $comment);

            return;
        }
    }

    private function fetchCommentProductAndUser(object $buttonPayload): array
    {
        $comment = Comment::find($buttonPayload->commentId ?? null);
        $product = Product::find($buttonPayload->productId ?? null);

        if (! $comment || ! $product) {
            return [null, null, null]; // Return null values if either is missing
        }

        // @phpsta@phpstan-ignore-next-line
        $customer = $this->smartCartClient->customer(['name' => $comment->commenter]);
        \info('Created/found customer in SmartCart', ['customer' => $customer]);

        // @phpstan-ignore-next-line
        $user = ['name' => $comment->commenter, 'id' => $comment->facebook_user_id, 'type' => 'messenger', 'sc_id' => $customer['id']];
        \info('Prepared user data for reservation / wishlist', ['user' => $user]);

        return [$comment, $product, $user];
    }
}
