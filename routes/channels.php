<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Each demo workspace has one private channel. Only its active owner may listen.
*/
Broadcast::channel('workspace.{userId}', function (User $user, int $userId): bool {
    return $user->isActiveDemo() && $user->id === $userId;
});
