<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Models\PrivateMessage;
use Illuminate\Support\Collection;

class PromptReserveProduct
{
    public function __construct(private Facebook $facebook) {}

    /**
     * @param  Collection<int, Product>  $products
     */
    public function execute(array $recipient, Collection $products, Comment $comment): void
    {
        $message = $products->count() > 1
            ? $this->buildCarouselMessage($recipient, $products, $comment)
            : $this->buildSingleProductMessage($recipient, $products->first(), $comment);
        $this->sendPrivateMessage($comment, $message);
    }

    private function sendPrivateMessage(Comment $comment, array $message): void
    {
        $messageRes = $this->facebook->sendMessage($message);
        $message = PrivateMessage::create([
            'comment_id' => $comment->id,
            'page_id' => $comment->getPageId(),
            'recipient_id' => $messageRes['recipient_id'],
            'message_id' => $messageRes['message_id'],
            'message' => $message,
        ]);

        \logger()->info('Message sent successfully.', ['comment_id' => $comment->id, 'message_id' => $message->id]);
    }

    private function buildSingleProductMessage(array $recipient, Product $product, Comment $comment): array
    {
        \info('Building single product message for customer and reserved comment.', ['comment_id' => $comment->id]);
        $element = [
            'title' => $product->name,
            'image_url' => $product->image_url,
            'subtitle' => 'Do you want to reserve this product in your invoice?',
            'default_action' => [
                'type' => 'web_url',
                'url' => $product->storeUrl(),
                'webview_height_ratio' => 'tall',
            ],
            'buttons' => [
                [
                    'type' => 'postback',
                    'title' => 'Reserve',
                    'payload' => json_encode([
                        'action' => 'RESERVE_PRODUCT',
                        'productId' => $product->id,
                        'commentId' => $comment->id,
                    ]),
                ],
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

    /**
     * @param  Collection<int, Product>  $products
     */
    private function buildCarouselMessage(array $recipient, Collection $products, Comment $comment): array
    {
        \info('Building a multiple products carousel message for customer and reserved comment.', ['comment_id' => $comment->id]);
        $elements = $products->map(function (Product $product) use ($comment) {
            return [
                'title' => $product->name,
                'image_url' => $product->image_url,
                'subtitle' => 'Do you want to reserve this product in your invoice?',
                'default_action' => [
                    'type' => 'web_url',
                    'url' => $product->storeUrl(),
                    'webview_height_ratio' => 'tall',
                ],
                'buttons' => [
                    [
                        'type' => 'postback',
                        'title' => 'Reserve',
                        'payload' => json_encode([
                            'action' => 'RESERVE_PRODUCT',
                            'productId' => $product->id,
                            'commentId' => $comment->id,
                        ]),
                    ],
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
}
