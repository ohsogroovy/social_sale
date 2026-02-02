<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Handlers\HandlePostWebhook;
use App\Handlers\HandleCommentsWebhook;
use App\Handlers\Facebook\MessageHandler;

class FacebookWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Handle Facebook webhook verification (GET)
        if ($request->isMethod('get')) {
            \logger()->info('Facebook Webhook Verification Request', $request->all());
            $mode = $request->input('hub_mode');
            $token = $request->input('hub_verify_token');
            $challenge = $request->input('hub_challenge');
            if ($mode === 'subscribe' && $token === env('FB_VERIFY_TOKEN')) {
                return response($challenge, 200);
            }
            return response('Forbidden', 403);
        }

        // Handle Facebook webhook events (POST)
        $payload = $request->all();
        \logger()->debug('Facebook webhook received', $payload);
        if (($payload['object'] ?? null) !== 'page') {
            \logger()->debug('Object type is not page.', $payload);
            return response('Ignored', 200);
        }

        foreach ($payload['entry'] as $entry) {
            foreach ($entry['messaging'] ?? [] as $message) {
                \app(MessageHandler::class)->handle($message);
            }
            foreach ($entry['changes'] ?? [] as $change) {
                $this->handleChange($change);
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    public function verify(Request $request)
    {
        $verify_token = env('FB_VERIFY_TOKEN');
        if ($request->input('hub_verify_token') === $verify_token) {
            return response($request->input('hub_challenge'), 200);
        }
        return response('Error, invalid token', 403);
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
