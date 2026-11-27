<?php

namespace App\Http\Controllers;

use App\Actions\RecordMcpAuthorization;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport's approval, plus a record of when the user granted access.
 *
 * It replaces Passport's controller through the container (see AppServiceProvider), so the route,
 * its middleware and the approval itself are unchanged. It only runs when a user presses Approve:
 * when Passport skips the consent screen for a client that still holds a token, no date changes.
 *
 * It relies on Passport's controller and its protected session method;
 * tests/Feature/McpClientRecordsTest.php fails if an upgrade changes either.
 */
class ApproveMcpAuthorizationController extends ApproveAuthorizationController
{
    private ?AuthorizationRequestInterface $authRequest = null;

    public function __construct(AuthorizationServer $server, private RecordMcpAuthorization $authorizations)
    {
        parent::__construct($server);
    }

    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $this->authRequest = null;

        // Throws unless the approval succeeds.
        $response = parent::approve($request, $psrResponse);

        if ($this->authRequest !== null) {
            // The user and client come from the request Passport validated and kept in the session,
            // never from the submitted form. A failure here must not undo an approval already given.
            rescue(fn () => $this->authorizations->record(
                $this->authRequest->getUser()->getIdentifier(),
                $this->authRequest->getClient()->getIdentifier(),
            ));
        }

        return $response;
    }

    protected function getAuthRequestFromSession(Request $request): AuthorizationRequestInterface
    {
        return $this->authRequest = parent::getAuthRequestFromSession($request);
    }
}
