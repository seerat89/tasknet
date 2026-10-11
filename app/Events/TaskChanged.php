<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsToWorkspace;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TaskChanged implements ShouldBroadcastNow
{
    use BroadcastsToWorkspace;

    /**
     * Get the name the event is broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'task.changed';
    }
}
