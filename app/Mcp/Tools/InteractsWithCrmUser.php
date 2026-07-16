<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Mirrors the `hasRole` and `role:admin` middleware guarding the web routes,
 * so an MCP client never gets more access than the same user has in the UI.
 */
trait InteractsWithCrmUser
{
    /**
     * The authenticated CRM user, or null when they may not use the CRM at all.
     */
    protected function crmUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User && $user->hasAnyRole() ? $user : null;
    }

    protected function accessDenied(): Response
    {
        return Response::error('Tvůj účet nemá v CRM přiřazenou roli, takže k těmto datům nemáš přístup.');
    }

    protected function adminRequired(): Response
    {
        return Response::error('Správu služeb může provádět pouze administrátor.');
    }
}
