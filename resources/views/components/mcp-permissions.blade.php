{{-- What an OAuth scope lets a client do, in the words shown on the consent screen. --}}
@props(['scopes' => ['mcp:use']])

@if (array_intersect(['mcp:use', '*'], $scopes))
    <ul {{ $attributes->class('flex list-disc flex-col gap-1 pl-5') }}>
        <li>View your projects and tasks</li>
        <li>Create and update projects and tasks</li>
    </ul>
@else
    <p {{ $attributes }}>No permission to use TaskNet’s MCP tools</p>
@endif
