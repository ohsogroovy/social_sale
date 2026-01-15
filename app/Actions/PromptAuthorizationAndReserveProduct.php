<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Clients\SmartCart;
use App\Models\PrivateMessage;
use Illuminate\Support\Collection;

class PromptAuthorizationAndReserveProduct
{
    public function __construct(private Facebook $facebook) {}

    /**
     * @param  Collection<int, Product>  $products
     */
    public function execute(array $user, Collection $products, Comment $comment): void
    {
        \logger()->info('Sending message to comment from reserve.', ['comment_id' => $comment->id]);

        $message = $products->count() > 1
            ? $this->buildCarouselMessage($user, $products, $comment)
            : $this->buildSingleProductMessage($user, $products->first(), $comment);
        $this->sendPrivateMessage($comment, $message);
        // $this->sendPrivateMessage($comment, $this->buildMessage($user, $product, $comment));
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
        \info('Building a multiple products carousel message for non-customers but reserved comment.', ['comment_id' => $comment->id]);
        $elements = $products->map(function (Product $product) use ($comment) {
            $variant = $product->variants()->first();

            return [
                'title' => $product->name,
                'image_url' => $product->image_url,
                'subtitle' => 'Click the button below to authorize with us and reserve the product.',
                'default_action' => [
                    'type' => 'web_url',
                    'url' => $product->storeUrl(),
                    'webview_height_ratio' => 'tall',
                ],
                'buttons' => [
                    [
                        'type' => 'web_url',
                        'url' => SmartCart::authorizationUrl(['name' => $comment->commenter, 'product' => $product->shopify_id, 'variant' => $variant->shopify_id]),
                        'title' => 'Click Here',
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

    private function buildSingleProductMessage(array $recipient, Product $product, Comment $comment): array
    {
        \info('Building a single product message for non-customers but reserved comment.', ['comment_id' => $comment->id]);
        $element = [
            'title' => $product->name,
            'image_url' => $product->image_url,
            'subtitle' => 'Click the button below to authorize with us and reserve the product.',
            'default_action' => [
                'type' => 'web_url',
                'url' => $product->storeUrl(),
                'webview_height_ratio' => 'tall',
            ],
        ];
        $variant = $product->variants()->first();
        $element['buttons'] = [
            [
                'type' => 'web_url',
                'url' => SmartCart::authorizationUrl(['name' => $comment->commenter, 'product' => $product->shopify_id, 'variant' => $variant->shopify_id]),
                'title' => 'Click Here',
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
