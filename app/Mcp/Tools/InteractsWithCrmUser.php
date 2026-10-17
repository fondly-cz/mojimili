<?php

namespace App\Mcp\Tools;

use App\Models\Contracts\ProjectRestricted;
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

        if (! $user instanceof User || ! $user->hasAnyRole()) {
            return null;
        }

        // The change history credits whoever is signed in; over MCP that is the token's owner.
        auth()->setUser($user);

        return $user;
    }

    protected function accessDenied(): Response
    {
        return Response::error('Tvůj účet nemá v CRM přiřazenou roli, takže k těmto datům nemáš přístup.');
    }

    /**
     * Mirrors the `projectAccess` middleware: a project and everything in it exists only for the
     * people assigned to it (and administrators).
     */
    protected function canSee(User $user, ?ProjectRestricted $record): bool
    {
        return $record !== null && $record->isVisibleTo($user);
    }

    protected function projectHidden(): Response
    {
        return Response::error('Záznam neexistuje, nebo patří do projektu, ke kterému nejsi přiřazen(a). Dostupné projekty vypíše list-projects.');
    }

    protected function assigneeNotMember(): Response
    {
        return Response::error('Řešitelem může být jen osoba přiřazená k projektu. Přiřaď ji nástrojem update-project-members, členy vypíše get-project.');
    }

    protected function adminRequired(): Response
    {
        return Response::error('Správu služeb může provádět pouze administrátor.');
    }
}
