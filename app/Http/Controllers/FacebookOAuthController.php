<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Clients\Facebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class FacebookOAuthController extends Controller
{
    public function begin()
    {
        /** @var User $user */
        $user = Auth::user();
        Session::put('auth_user', $user);

        return redirect(Facebook::oauthBeginUrl(\secure_url('/auth/facebook/callback')));
    }

    public function callback(Request $request)
    {
        /** @var User $user */
        $user = Session::get('auth_user');

        $accessToken = Facebook::getAccessToken($request->code, \secure_url('/auth/facebook/callback'));

        $user->update([
            'facebook_user_token' => $accessToken,
        ]);
        $facebookClient = new Facebook;
        $facebookUser = $facebookClient->getUserFromToken();
        $authorizedPages = $facebookClient->getAuthorizedPages();

        $user->update([
            'facebook_user_id' => $facebookUser['id'],
            'facebook_page_token' => data_get($authorizedPages, 'data.0.access_token'),
            'facebook_page_id' => data_get($authorizedPages, 'data.0.id'),
        ]);
        logger('User data updated successfully from facebook');

        return redirect('/facebook/subscribe-webhooks')->with('success', 'Facebook account linked successfully.');
    }

    public function subscribeWebhooks(Facebook $facebookClient)
    {
        $facebookClient->subscribeWebhooks();

        return redirect('/');
    }
}
