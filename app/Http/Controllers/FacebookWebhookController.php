<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Handlers\HandlePostWebhook;
use App\Handlers\HandleCommentsWebhook;
use App\Handlers\Facebook\MessageHandler;

class FacebookWebhookController extends Controller
{
    public function verify(Request $request)
    {
        \logger()->info('Facebook Webhook Verification Request', $request->all());
        // Support both dot and underscore parameter names
        $mode = $request->input('hub_mode', $request->input('hub.mode'));
        $token = $request->input('hub_verify_token', $request->input('hub.verify_token'));
        $challenge = $request->input('hub_challenge', $request->input('hub.challenge'));

        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === env('FACEBOOK_VERIFY_TOKEN')) {
                return response($challenge, 200);
            } else {
                return response('Forbidden', 403);
            }
        }

        return response('Bad Request', 400);
    }

    public function handle(Request $request)
    {
        $payload = $request->all();
        \logger()->debug('Facebook webhook received');
        if ($payload['object'] !== 'page') {
            \logger()->debug('Object type is not page.', $payload);

            return;
        }

        foreach ($payload['entry'] as $entry) {
            foreach ($entry['messaging'] ?? [] as $message) {
                \app(MessageHandler::class)->handle($message);
            }

            foreach ($entry['changes'] ?? [] as $change) {
                $this->handleChange($change);
            }
        }

        return \response('OK');
    }

    protected function handleChange(array $change)
    {
        $itemType = $change['value']['item'];
        $handlerClass = match ($itemType) {
            'comment' => HandleCommentsWebhook::class,
            'status', 'photo', 'video' => HandlePostWebhook::class,
            default => null,
        };

        if ($handlerClass) {
            app($handlerClass)->execute($change);
        } else {
            \logger()->info('Unhandled webhook', ['type' => $itemType]);
        }
    }
}
