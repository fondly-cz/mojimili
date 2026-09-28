<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->string('priority')->nullable()->after('name');
            $table->unsignedInteger('estimated_minutes')->nullable()->after('days');
            // Optional time of day for the due date; the date alone stays the recurrence anchor.
            $table->time('due_time')->nullable()->after('due_date');
            $table->foreignId('created_by_user_id')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropColumn(['priority', 'estimated_minutes', 'due_time']);
        });
    }
};
