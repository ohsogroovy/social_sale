<?php

namespace App\Clients;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class Facebook
{
    private string $version = 'v20.0';

    private ?User $user;

    private PendingRequest $http;

    public function __construct()
    {
        $this->user = User::first();
        $baseUrl = "https://graph.facebook.com/$this->version";
        $this->http = Http::withHeaders([
            'Authorization' => "Bearer {$this->user->facebook_page_token}",
        ])->connectTimeout(60)->timeout(60)->retry(3, 30)->throw()->baseUrl($baseUrl);
    }

    public static function oauthBeginUrl(string $redirectUrl): string
    {
        $facebookConfig = \config('facebook');
        $queryParams = [
            'client_id' => $facebookConfig['app_id'],
            'redirect_uri' => $redirectUrl,
            'state' => \Illuminate\Support\Str::random(40),
            'scope' => implode(',', $facebookConfig['scopes']),
            'response_type' => 'code',
        ];

        return 'https://www.facebook.com/v20.0/dialog/oauth?'.http_build_query($queryParams);
    }

    public static function getAccessToken(string $code, string $redirectUrl): string
    {
        $facebookConfig = \config('facebook');
        $queryParams = [
            'client_id' => $facebookConfig['app_id'],
            'redirect_uri' => $redirectUrl,
            'client_secret' => $facebookConfig['app_secret'],
            'code' => $code,
        ];

        return Http::retry(3)->throw()->get('https://graph.facebook.com/v20.0/oauth/access_token', $queryParams)->json('access_token');
    }

    public function getUserFromToken(): array
    {
        return $this->http->get('/me', ['fields' => 'id,name,email,picture', 'access_token' => $this->user->facebook_user_token])->json();
    }

    public function getAuthorizedPages(): array
    {
        return $this->http->get('/me/accounts', ['access_token' => $this->user->facebook_user_token])->json();
    }

    public function subscribeWebhooks(): array
    {
        return $this->http->post("/{$this->user->facebook_page_id}/subscribed_apps", ['subscribed_fields' => 'feed,group_feed,messages,message_reactions,messaging_postbacks'])->json();
    }

    public function getLiveStreams(): array
    {
        return $this->http->get("/{$this->user->facebook_page_id}/live_videos")->json();
    }

    public function getComments(int $liveVideoId): array
    {
        return $this->http->get("/{$liveVideoId}/comments", ['order' => 'chronological', 'filter' => 'stream'])->json();
    }

    public function getPagePublishedPosts(int $limit): array
    {
        return $this->http->get("/{$this->user->facebook_page_id}/published_posts", ['limit' => $limit])->json();
    }

    public function getPostData(string $postId): array
    {
        return $this->http->get("/{$postId}")->json();
    }

    public function sendMessage(array $message): array
    {
        return $this->http->post("/{$this->user->facebook_page_id}/messages", $message)->json();
    }

    public function messageAttachment(array $message): array
    {
        return $this->http->post("/{$this->user->facebook_page_id}/message_attachments", $message)->json();
    }

    public function postComment(string $postId, array $data): array
    {
        return $this->http->post("/{$postId}/comments", $data)->json();
    }

    public function getPostAttachments(string $postId): array
    {
        return $this->http->get("/{$postId}/attachments")->json('data');
    }
}
