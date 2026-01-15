<?php

use Carbon\Carbon;
use App\Models\Comment;
use App\Actions\UpdateLiveStreamStatus;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (UpdateLiveStreamStatus $updateLiveStreamStatus) {
    $updateLiveStreamStatus->execute();
})->everyFiveSeconds()->name('update-live-stream-status');

Schedule::call(function () {
    $date = Carbon::now()->subDays(30);
    Comment::whereHas('post', function ($query) {
        $query->where('post_type', '!=', 'live');
    })->where('facebook_created_at', '<', $date)->chunk(100, function ($comments) {
        foreach ($comments as $comment) {
            $comment->delete();
        }
    });
})->daily()->name('delete-old-comments');
