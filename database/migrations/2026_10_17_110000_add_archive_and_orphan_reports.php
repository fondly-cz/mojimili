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
        Schema::table('todolists', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('sort_order');
        });

        // A report can outlive its todo (when the list is deleted but the reported time is kept),
        // so it carries its own project and the names it was reported under.
        Schema::table('work_reports', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('todo_id')->constrained()->cascadeOnDelete();
            $table->string('todo_name')->nullable()->after('project_id');
            $table->string('todolist_name')->nullable()->after('todo_name');
        });

        Schema::table('work_reports', function (Blueprint $table) {
            $table->dropForeign(['todo_id']);
        });

        Schema::table('work_reports', function (Blueprint $table) {
            $table->foreignId('todo_id')->nullable()->change();
            $table->foreign('todo_id')->references('id')->on('todos')->cascadeOnDelete();
        });

        foreach (DB::table('work_reports')
            ->join('todos', 'todos.id', '=', 'work_reports.todo_id')
            ->join('todolists', 'todolists.id', '=', 'todos.todolist_id')
            ->select('work_reports.id', 'todolists.project_id', 'todos.name as todo_name', 'todolists.name as todolist_name')
            ->get() as $row) {
            DB::table('work_reports')->where('id', $row->id)->update([
                'project_id' => $row->project_id,
                'todo_name' => $row->todo_name,
                'todolist_name' => $row->todolist_name,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('work_reports')->whereNull('todo_id')->delete();

        Schema::table('work_reports', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn(['project_id', 'todo_name', 'todolist_name']);
        });

        Schema::table('todolists', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
