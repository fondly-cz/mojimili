<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The history outlives what it describes, so project/todolist/todo are plain ids, not foreign keys.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('todolist_id')->nullable();
            $table->unsignedBigInteger('todo_id')->nullable();
            $table->string('event');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'id']);
            $table->index(['todo_id', 'id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
