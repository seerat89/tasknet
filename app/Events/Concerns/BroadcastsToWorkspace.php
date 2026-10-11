<?php

namespace App\Events\Concerns;

use App\Enums\ChangeType;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * Shared behavior for events announced on a demo workspace's private channel.
 *
 * Events carry only the workspace owner's id (to pick the channel) and the kind of change.
 */
trait BroadcastsToWorkspace
{
    use Dispatchable;

    public function __construct(public readonly int $userId, public readonly ChangeType $change) {}

    /**
     * Announce a change in the user's workspace.
     *
     * The change has already been saved, so a realtime outage is reported
     * instead of failing the web or MCP request that made the change.
     */
    public static function announce(User $user, ChangeType $change): void
    {
        try {
            static::dispatch($user->id, $change);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Get the private channel for the workspace.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspace.'.$this->userId)];
    }

    /**
     * Get the data to broadcast. Never include models, so no user data can leak.
     *
     * @return array{change: string}
     */
    public function broadcastWith(): array
    {
        return ['change' => $this->change->value];
    }
}
