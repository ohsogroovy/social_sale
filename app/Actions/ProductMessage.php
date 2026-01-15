<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Models\PrivateMessage;
use Illuminate\Support\Collection;

class ProductMessage
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
     */
    private function buildCarouselMessage(array $recipient, Collection $products, Comment $comment): array
    {
        \info('Building a multiple products carousel message for non-customers and non-reserved comment.', ['comment_id' => $comment->id]);
        $elements = $products->map(function (Product $product) {
            return [
                'title' => $product->name,
                'image_url' => $product->image_url,
                'subtitle' => $product->short_description,
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

    private function buildSingleProductMessage(array $recipient, Product $product, Comment $comment): array
    {
        \info('Building single product message for non-customer and non-reserved comment.', ['comment_id' => $comment->id]);
        $element = [
            'title' => $product->name,
            'image_url' => $product->image_url,
            'subtitle' => $product->short_description,
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
