<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Support\UriTemplate;

/**
 * Serves the current page for any other build's address.
 *
 * Clients cache tool metadata, so after a deployment some still ask for the previous URI. An exact match
 * with TaskNetApp wins; this template only answers when there is none. No tool points here.
 */
#[Name('tasknet-app-any-build')]
#[Description('The current TaskNet view, for clients that still hold the address of an earlier build.')]
class TaskNetAppAnyBuild extends TaskNetApp implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('ui://tasknet/app-{build}.html');
    }

    public function uri(): string
    {
        return (string) $this->uriTemplate();
    }
}
