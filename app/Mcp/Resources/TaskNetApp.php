<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * The interactive TaskNet view shared by the show_* tools, built from resources/mcp-app.
 *
 * It declares no CSP domains, so hosts apply their strictest policy: the page may load nothing and
 * connect nowhere. It reads and changes data only by asking the host to call TaskNet's tools.
 *
 * The built file is kept outside public/, so it is only ever served here, to an authorized client.
 *
 * Hosts cache the page by its URI, so the URI carries a hash of the built file and changes whenever the
 * page does. TaskNetAppAnyBuild answers the addresses of other builds.
 */
#[Name('tasknet-app')]
#[Title('TaskNet')]
#[Description('Interactive view of the projects and tasks in your TaskNet workspace.')]
class TaskNetApp extends AppResource
{
    public function uri(): string
    {
        $path = $this->path();
        $build = is_file($path) ? substr((string) hash_file('sha256', $path), 0, 12) : 'unbuilt';

        return "ui://tasknet/app-{$build}.html";
    }

    /**
     * Serve the self-contained page produced by `npm run build`.
     */
    public function handle(): Response
    {
        return Response::html($this->path());
    }

    /**
     * Leave out `ui.domain`, which Laravel MCP would otherwise fill in using Claude's format.
     *
     * The field is optional and its format differs between hosts. This view needs no stable origin,
     * so every host can use its default sandbox.
     *
     * @return array<string, mixed>
     */
    public function resolvedAppMeta(): array
    {
        return $this->appMeta()->toArray();
    }

    /**
     * Where vite.mcp.config.ts writes the page.
     */
    protected function path(): string
    {
        return resource_path('dist/mcp-app/app.html');
    }
}
