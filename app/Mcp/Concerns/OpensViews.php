<?php

namespace App\Mcp\Concerns;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Shared by the show_* tools, which open the interactive view and change nothing.
 */
trait OpensViews
{
    /**
     * Ask the client to open the interactive view at a starting point. The view then loads its own data.
     *
     * The text is what the model reads, including in clients that cannot show interactive views.
     *
     * @param  array<string, int>  $ids
     */
    protected function openView(string $view, string $subject, string $alternative, array $ids = []): ResponseFactory
    {
        return Response::make(Response::text(
            "Opened {$subject} in the TaskNet view. Nothing was changed. If this client cannot show interactive views, use {$alternative} instead.",
        ))->withStructuredContent(['view' => $view, ...$ids]);
    }
}
