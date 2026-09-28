<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Labels are shared across all projects, as task labels in Freelo.
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->default('#77787a');
            $table->uuid('freelo_uuid')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('label_todo', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->primary(['label_id', 'todo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_todo');
        Schema::dropIfExists('labels');
    }
};
