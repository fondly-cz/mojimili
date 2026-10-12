<?php

namespace App\Http\Middleware;

use App\Models\Contracts\ProjectRestricted;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectAccess
{
    /**
     * A project and everything in it (lists, todos, comments, files, work reports) exists only
     * for the people assigned to it; for anyone else the address answers as if it was not there.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof ProjectRestricted && ! $parameter->isVisibleTo($request->user())) {
                abort(404);
            }
        }

        return $next($request);
    }
}
