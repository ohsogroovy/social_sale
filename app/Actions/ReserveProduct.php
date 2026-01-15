<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Clients\SmartCart;
use App\Models\PrivateMessage;
use Illuminate\Support\Collection;

class ReserveProduct
{
    public function __construct(
        private SmartCart $smartCartClient,
        private Facebook $facebook,
        private SendWaitMessage $sendWaitMessage
    ) {}

    /**
     * @param  Collection<int, Product>  $products
     */
    public function execute(array $user, Collection $products, Comment $comment): void
    {
        $productResponses = $products->mapWithKeys(function (Product $product) use ($user, $comment) {
            $response = $this->smartCartClient->reserveProduct($user['sc_id'], $product->shopify_id);
            \info("Reservation response for product {$product->id}", ['response' => $response]);

            $hasWaitListTag = $product->tags->contains(function ($tag) {
                return str_contains(strtolower($tag->name), 'wait');
            });

            if ($response['error'] && $hasWaitListTag) {
                $this->sendWaitMessage->execute($user, $product, $comment, $response);

                return [];
            }

            return [$product->id => [
                'product' => $product,
                'response' => $response,
            ]];
        })->filter();

        if ($productResponses->isEmpty()) {
            return;
        }

        $orderedProducts = $productResponses->pluck('product');

        \logger()->info('Sending reservation message to comment.', ['comment_id' => $comment->id]);

        $message = $orderedProducts->count() > 1
            ? $this->buildCarouselMessage($user, $orderedProducts, $comment, $productResponses)
            : $this->buildSingleProductMessage($user, $orderedProducts->first(), $comment, $productResponses);
        $this->sendPrivateMessage($comment, $message);
    }

    private function sendPrivateMessage(Comment $comment, array $message): void
    {
        $messageRes = $this->facebook->sendMessage($message);
        \logger()->info('Message response', ['response' => $messageRes]);
        $message = PrivateMessage::create([
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageRes['recipient_id'],
            'message_id' => $messageRes['message_id'],
            'message' => $message,
        ]);

        \logger()->info('Message sent successfully.', ['comment_id' => $comment->id, 'message_id' => $message->id]);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, array{product: Product, response: array}>  $productResponses
     */
    private function buildCarouselMessage(array $recipient, Collection $products, Comment $comment, Collection $productResponses): array
    {
        \info('Building a multiple products carousel message for reserved products.', ['comment_id' => $comment->id]);
        $elements = $products->map(function (Product $product) use ($productResponses) {
            $response = $productResponses[$product->id]['response'] ?? ['error' => false];
            $subtitle = $response['error']
                ? ($response['message'] ?? 'There was an error reserving this product.')
                : 'The product has been reserved. Please check your cart.';

            return [
                'title' => $product->name,
                'image_url' => $product->image_url,
                'subtitle' => $subtitle,
                'default_action' => [
                    'type' => 'web_url',
                    'url' => $product->storeUrl(),
                    'webview_height_ratio' => 'tall',
                ],
            ];
        })->toArray();

        return [
            'recipient' => $recipient['type'] == 'commenter' ? ['comment_id' => $recipient['id']] : ['id' => $recipient['id']],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'generic',
                        'elements' => $elements,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, array{product: Product, response: array}>  $productResponses
     */
    private function buildSingleProductMessage(array $recipient, Product $product, Comment $comment, Collection $productResponses): array
    {
        \info('Building a single product message for reserved product.', ['comment_id' => $comment->id]);
        $response = $productResponses[$product->id]['response'] ?? ['error' => false];
        $subtitle = $response['error']
            ? ($response['message'] ?? 'There was an error reserving this product.')
            : 'The product has been reserved. Please check your cart.';

        $element = [
            'title' => $product->name,
            'image_url' => $product->image_url,
            'subtitle' => $subtitle,
            'default_action' => [
                'type' => 'web_url',
                'url' => $product->storeUrl(),
                'webview_height_ratio' => 'tall',
            ],
        ];

        return [
            'recipient' => $recipient['type'] == 'commenter' ? ['comment_id' => $recipient['id']] : ['id' => $recipient['id']],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'generic',
                        'elements' => [$element],
                    ],
                ],
            ],
        ];
    }
}
