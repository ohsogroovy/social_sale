<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\SmartCart;

class AddToWaitList
{
    public function __construct(private SmartCart $smartCartClient, private SendReplyMessage $sendReplyMessage) {}

    public function execute(array $user, Product $product, Comment $comment): void
    {
        $response = $this->smartCartClient->addProductToWaitList($user['sc_id'], $product->shopify_id);
        \info('Adding product to waitlist', ['response' => $response]);

        $product = collect([$product]);
        $this->sendReplyMessage->execute($user, $product, $comment, $response);
    }
}
