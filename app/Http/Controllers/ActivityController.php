<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    private const PAGE = 30;

    public function project(Request $request, Project $project): JsonResponse
    {
        return $this->page($request, Activity::where('project_id', $project->id));
    }

    public function todo(Request $request, Todo $todo): JsonResponse
    {
        return $this->page($request, Activity::where('todo_id', $todo->id));
    }

    /**
     * Newest first; `before` is the id of the last entry the client already has.
     *
     * @param  Builder<Activity>  $query
     */
    private function page(Request $request, $query): JsonResponse
    {
        $entries = $query
            ->visibleTo($request->user())
            ->when($request->integer('before'), fn ($q, $before) => $q->where('activities.id', '<', $before))
            ->with('user:id,name,avatar')
            ->orderByDesc('activities.id')
            ->limit(self::PAGE + 1)
            ->get();

        return response()->json([
            'data' => $entries->take(self::PAGE)->values(),
            'has_more' => $entries->count() > self::PAGE,
        ]);
    }
}
