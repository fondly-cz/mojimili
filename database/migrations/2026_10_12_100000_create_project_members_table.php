<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // People assigned to a project; only they can be mentioned in its todos.
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        // Existing projects start with everyone who already takes part in them.
        $todos = fn () => DB::table('todos')->join('todolists', 'todolists.id', '=', 'todos.todolist_id');

        $participants = [
            DB::table('projects')->select('id as project_id', 'user_id'),
            DB::table('project_user_rates')->select('project_id', 'user_id'),
            $todos()->select('todolists.project_id', 'todos.assigned_user_id as user_id'),
            $todos()->select('todolists.project_id', 'todos.created_by_user_id as user_id'),
            $todos()->join('todo_comments', 'todo_comments.todo_id', '=', 'todos.id')
                ->select('todolists.project_id', 'todo_comments.user_id'),
            $todos()->join('work_reports', 'work_reports.todo_id', '=', 'todos.id')
                ->select('todolists.project_id', 'work_reports.user_id'),
        ];

        $now = now();

        foreach ($participants as $query) {
            $rows = $query->distinct()->get()
                ->filter(fn ($row) => $row->user_id !== null)
                ->map(fn ($row) => [
                    'project_id' => $row->project_id,
                    'user_id' => $row->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($rows->chunk(500) as $chunk) {
                DB::table('project_members')->insertOrIgnore($chunk->all());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
