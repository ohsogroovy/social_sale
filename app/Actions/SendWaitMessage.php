<?php

namespace App\Actions;

use App\Models\Comment;
use App\Models\Product;
use App\Clients\Facebook;
use App\Models\PrivateMessage;

class SendWaitMessage
{
    public function __construct(private Facebook $facebook) {}

    public function execute(array $recipient, Product $product, Comment $comment, array $messageDetails): void
    {
        $this->sendPrivateMessage($comment, $this->buildMessage($recipient, $product, $comment));
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

    private function buildMessage(array $recipient, Product $product, Comment $comment): array
    {
        \info('Building wait list message.', ['product' => $product->id, 'comment' => $comment->id, 'recipient' => $recipient]);
        $options = [
            ['title' => 'Add to waitlist', 'action' => 'ADD_TO_WAITLIST'],
        ];
        $element = [
            'title' => $product->name,
            'image_url' => $product->image_url,
            'subtitle' => 'Product is out of stock. Do you want to add it to the waitlist?',
            'default_action' => [
                'type' => 'web_url',
                'url' => $product->storeUrl(),
                'webview_height_ratio' => 'tall',
            ],
            'buttons' => array_map(function ($option) use ($product, $comment) {
                return [
                    'type' => 'postback',
                    'title' => $option['title'],
                    'payload' => json_encode([
                        'action' => $option['action'],
                        'productId' => $product->id,
                        'commentId' => $comment->id,
                    ]),
                ];
            }, $options),
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
