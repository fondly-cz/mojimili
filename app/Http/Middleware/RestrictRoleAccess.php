<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RestrictRoleAccess
{
    /**
     * A customer only reads their projects and writes comments, so everything else is closed to them.
     *
     * @var list<string>
     */
    private const CUSTOMER_ROUTES = [
        'dashboard', 'projects.index', 'projects.show', 'todolists.show', 'todos.show', 'todos.activity',
        'todo-attachments.show', 'todo-comment-attachments.show',
        'todos.comments.store', 'todo-comments.update', 'todo-comments.destroy',
        'profile.*', 'logout',
    ];

    /**
     * Money, customers and administration stay with administrators and managers; a collaborator
     * works on projects and reports their own time, nothing more.
     *
     * @var list<string>
     */
    private const COLLABORATOR_CLOSED_ROUTES = [
        'companies.*', 'calculations.*', 'billing.*', 'invoices.*', 'services.*', 'users.*', 'passport-keys.*',
        'my-company.*', 'ares.*', 'search', 'api.*', 'labels.*',
        'projects.create', 'projects.store', 'projects.edit', 'projects.update', 'projects.destroy', 'projects.bulk-delete',
        'projects.members.*', 'projects.invitations.*', 'projects.documents.*', 'project-documents.*',
        'projects.notes.*', 'project-notes.*',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route()?->getName() ?? '';

        if ($user?->isCustomer() && ! Str::is(self::CUSTOMER_ROUTES, $route)) {
            abort(403, 'Tato role nemá k této akci přístup.');
        }

        if ($user?->isCollaborator() && Str::is(self::COLLABORATOR_CLOSED_ROUTES, $route)) {
            abort(403, 'Tato role nemá k této akci přístup.');
        }

        return $next($request);
    }
}
