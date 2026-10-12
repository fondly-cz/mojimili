<?php

namespace Tests;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * A project is visible only to the people assigned to it. Most tests are about something
     * else, so by default every user is put into every project; tests of the access rules
     * themselves turn this off.
     */
    protected bool $everyoneInEveryProject = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->everyoneInEveryProject) {
            return;
        }

        $assign = fn (iterable $projectIds, iterable $userIds) => DB::table('project_members')->insertOrIgnore(
            collect($projectIds)->crossJoin($userIds)
                ->map(fn ($pair) => ['project_id' => $pair[0], 'user_id' => $pair[1]])
                ->all()
        );

        Project::created(fn (Project $project) => $assign([$project->id], User::pluck('id')));
        User::created(fn (User $user) => $assign(Project::pluck('id'), [$user->id]));
    }
}
