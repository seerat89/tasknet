<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Get the scheduled event for an Artisan command.
 */
function scheduledEvent(string $command): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, $command));
}

test('both cleanup commands are scheduled daily', function () {
    foreach (['tasknet:cleanup-demos', 'tasknet:cleanup-oauth'] as $command) {
        $event = scheduledEvent($command);

        expect($event)->not->toBeNull()
            ->and($event->expression)->toMatch('/^\d+ \d+ \* \* \*$/');
    }
});

test('the cleanup commands do not start at the same time', function () {
    expect(scheduledEvent('tasknet:cleanup-oauth')->expression)
        ->not->toBe(scheduledEvent('tasknet:cleanup-demos')->expression);
});

test('Passport purge is not scheduled separately', function () {
    expect(scheduledEvent('passport:purge'))->toBeNull();
});
