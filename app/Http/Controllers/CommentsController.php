<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Comment;

class CommentsController extends Controller
{
    public function index()
    {
        $comments = Comment::query()
            ->where(function ($query) {
                $query->whereDoesntHave('post')
                    ->orWhereHas('post', function ($query) {
                        $query->where('post_type', '!=', 'live');
                    });
            })
            ->with('privateMessage:comment_id')
            ->select('id', 'commenter', 'post_type', 'post_link', 'facebook_id', 'parent_id', 'message', 'facebook_created_at')
            ->orderBy('facebook_created_at', 'desc')
            ->paginate();

        return Inertia::render('Activity/Show', [
            'comments' => $comments,
        ]);
    }
}
