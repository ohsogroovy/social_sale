<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Models\PrivateMessage;
use Illuminate\Support\Collection;

class SendReplyMessage
{
    public function __construct(private Facebook $facebook) {}

    /**
     * @param  Collection<int, Product>  $products
     */
    public function execute(array $recipient, Collection $products, Comment $comment, array $messageDetails): void
    {
        $message = $products->count() > 1
            ? $this->buildCarouselMessage($recipient, $products, $messageDetails)
            : $this->buildSingleProductMessage($recipient, $products->first(), $messageDetails);
        $this->sendPrivateMessage($comment, $message);

        // $this->sendPrivateMessage($comment, $this->buildMessage($recipient, $product, $messageDetails));
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
    private function buildCarouselMessage(array $recipient, Collection $products, array $messageDetails): array
    {
        \info('Building a multiple products carousel message for reserved products.');

        $elements = $products->map(function (Product $product) use ($messageDetails) {
            $productMessage = $messageDetails[$product->id] ?? [
                'error' => false,
                'message' => 'The product has been reserved. Please check your cart.',
                'is_wait_list' => false,
            ];

            return [
                'title' => $product->name,
                'image_url' => $product->image_url,
                'subtitle' => ($productMessage['error'] || ($productMessage['is_wait_list'] ?? false))
                    ? $productMessage['message']
                    : 'The product has been reserved. Please check your cart.',
                'webview_height_ratio' => 'tall',
            ];
        })->toArray();

        return [
            'recipient' => $recipient['type'] === 'commenter' ? ['comment_id' => $recipient['id']] : ['id' => $recipient['id']],
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

    private function buildSingleProductMessage(array $recipient, Product $product, array $messageDetails): array
    {
        \info('Processing messageDetails array for single product message', [
            'product_id' => $product->id,
            'messageDetails' => $messageDetails,
        ]);

        $productMessage = $messageDetails[$product->id] ?? $messageDetails;
        $element = [
            'title' => $product->name,
            'image_url' => $product->image_url,
            'subtitle' => ($productMessage['error'] || ($productMessage['is_wait_list'] ?? false))
                ? $productMessage['message']
                : 'The product has been reserved. Please check your cart.',
            'default_action' => [
                'type' => 'web_url',
                'url' => $product->storeUrl(),
                'webview_height_ratio' => 'tall',
            ],
        ];

        return [
            'recipient' => $recipient['type'] === 'commenter' ? ['comment_id' => $recipient['id']] : ['id' => $recipient['id']],
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
