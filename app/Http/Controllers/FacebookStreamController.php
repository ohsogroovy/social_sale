<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Inertia\Inertia;
use App\Models\Comment;
use App\Clients\Facebook;
use Illuminate\Http\Request;
use App\Actions\GetLatestLivePost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FacebookStreamController extends Controller
{
    public function getLiveStream()
    {
        $latestLiveStream = Post::where('is_live', true)
            ->where('post_type', 'live')
            ->with(['comments' => fn ($comments) => $comments->orderBy('facebook_created_at', 'desc')])
            ->first();

        return Inertia::render('LiveStreams/Show', [
            'current_live_streams' => $latestLiveStream,
        ]);
    }

    public function getLatestComments()
    {
        $latestLiveStream = Post::where('is_live', true)
            ->where('post_type', 'live')
            ->with(['comments' => fn ($comments) => $comments->orderBy('facebook_created_at', 'asc')])
            ->first();

        return response()->json([
            'error' => false,
            'message' => '',
            'data' => $latestLiveStream,
        ]);
    }

    public function showPostWithComments(Post $post)
    {
        $comments = $post->comments()->with('privateMessage:comment_id')
            ->select('id', 'commenter', 'post_id', 'post_link', 'facebook_id', 'message', 'facebook_created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Post/Show', [
            'post' => $post,
            'comments' => $comments,
        ]);
    }

    public function postComment(Request $request, Post $post, Facebook $facebookClient)
    {
        $validator = Validator::make($request->all(), [
            'message' => $request->all() ? 'nullable|string' : 'required|string',
            'attachment_id' => 'nullable|string',
            'attachment_share_url' => 'nullable|url',
            'attachment_url' => 'nullable|url',
            'source' => 'nullable|file|mimes:jpg,jpeg,png,gif',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->hasAny(['message', 'attachment_id', 'attachment_share_url', 'attachment_url', 'source'])) {
                $validator->errors()->add('message', 'At least one of message or attachment fields is required.');
            }
        })->validate();
        $post_id = $post->facebook_id;
        $data = $request->only(['message', 'attachment_id', 'attachment_share_url', 'attachment_url']);
        if ($request->hasFile('source')) {
            $data['source'] = $request->file('source');
        }

        $response = $facebookClient->postComment($post_id, $data);

        return response()->json($response);
    }

    public function getPastStreams()
    {
        $pastStreams = Post::where('is_live', false)->where('post_type', 'live')->latest('created_at')->paginate(10);

        return Inertia::render('PastStreams/Show', [
            'past-streams' => $pastStreams,
        ]);
    }

    public function searchComments(Request $request, Post $post)
    {
        $query = Comment::query()->where('post_id', $post->facebook_id);

        if ($request->has('search')) {
            $query->where('message', 'like', '%'.$request->input('search').'%');
        }

        $comments = $query
            ->select('id', 'commenter', 'facebook_created_at', 'post_type', 'post_link', 'facebook_id', 'message', 'created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->paginate();

        return response()->json([
            'comments' => $comments,
        ]);
    }

    public function syncManualLiveStream(GetLatestLivePost $getLatestLivePost)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'error' => true,
                'message' => 'User not authenticated',
                'data' => null,
            ], 401);
        }

        $result = $getLatestLivePost->execute($user);

        if ($result['success']) {
            return response()->json([
                'error' => false,
                'message' => 'Latest live stream found',
                'data' => [
                    'live_stream' => $result['data']['post'],
                    'facebook_data' => $result['data']['facebook_data'],
                    'sync_timestamp' => now(),
                ],
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'No live stream detected',
                'data' => null,
            ], 404);
        }
    }
}
