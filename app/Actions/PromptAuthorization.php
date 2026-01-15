<?php

namespace App\Actions;

use App\Models\Comment;
use App\Clients\Facebook;
use App\Clients\SmartCart;
use App\Models\PrivateMessage;

class PromptAuthorization
{
    public function __construct(private Facebook $facebook) {}

    public function execute(array $user, Comment $comment): void
    {
        \logger()->info('Sending message to comment from reserve.', ['comment_id' => $comment->id]);

        $this->sendPrivateMessage($comment, $this->buildMessage($user, $comment));
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

    private function buildMessage(array $recipient, Comment $comment): array
    {
        $element = [
            'title' => 'Authorize with Hotsy Totsy',
            'subtitle' => 'Click the button below to authorize with us',
            'buttons' => [
                [
                    'type' => 'web_url',
                    'url' => SmartCart::authorizationUrl(['name' => $comment->commenter]),
                    'title' => 'Authorize',
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
}
