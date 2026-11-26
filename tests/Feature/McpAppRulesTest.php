<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\StoreTaskRequest;

/*
 * The MCP App repeats a few server rules so it can give early feedback (resources/mcp-app/rules.ts).
 * These tests fail when the copy and the PHP definitions drift apart.
 */

function widgetRules(): string
{
    return (string) file_get_contents(resource_path('mcp-app/rules.ts'));
}

test('the widget uses the server length limits', function (string $record, string $request) {
    preg_match("/{$record}: \\{([^}]*)\\}/", widgetRules(), $block);
    preg_match_all('/(\w+): (\d+)/', $block[1] ?? '', $pairs);
    $widget = array_map('intval', array_combine($pairs[1], $pairs[2]));

    $server = collect((new $request)->rules())
        ->map(fn (array $rules) => collect($rules)->first(fn (mixed $rule) => is_string($rule) && str_starts_with($rule, 'max:')))
        ->filter()
        ->map(fn (string $rule) => (int) substr($rule, 4))
        ->all();

    expect($widget)->not->toBeEmpty()->toEqual($server);
})->with([
    ['project', StoreProjectRequest::class],
    ['task', StoreTaskRequest::class],
]);

test('the widget uses the server values and labels in the same order', function (string $values, string $labels, string $enum) {
    $source = widgetRules();

    preg_match("/{$values} = \\[([^\\]]*)\\]/", $source, $list);
    preg_match_all("/'(\w+)'/", $list[1] ?? '', $found);
    preg_match("/{$labels}[^=]*= \\{([^}]*)\\}/", $source, $map);
    preg_match_all("/(\w+): '([^']*)'/", $map[1] ?? '', $pairs);

    expect($found[1])->toBe(array_column($enum::cases(), 'value'))
        ->and(array_combine($pairs[1], $pairs[2]))->toBe(
            collect($enum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all(),
        );
})->with([
    ['STATUSES', 'STATUS_LABELS', TaskStatus::class],
    ['PRIORITIES', 'PRIORITY_LABELS', TaskPriority::class],
]);
