<?php

namespace App\Listeners;

use App\Events\CommentPosted;
use App\Actions\CoordinateMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMessageListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(private CoordinateMessage $coordinateMessage)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CommentPosted $event): void
    {
        $this->coordinateMessage->execute($event->comment);
    }
}
