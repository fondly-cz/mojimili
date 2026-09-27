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
        Schema::table('todos', function (Blueprint $table) {
            // Recurring todos work like Freelo: completing one spawns the next occurrence.
            $table->string('recurrence_frequency')->nullable()->after('due_date');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_frequency');
            $table->boolean('recurrence_working_days_only')->default(false)->after('recurrence_interval');
            $table->date('recurrence_ends_on')->nullable()->after('recurrence_working_days_only');
            // How many more occurrences may still be spawned; null = no limit.
            $table->unsignedInteger('recurrence_remaining')->nullable()->after('recurrence_ends_on');
            $table->boolean('recurrence_copy_description')->default(true)->after('recurrence_remaining');
            $table->foreignId('recurrence_previous_id')->nullable()->after('recurrence_copy_description')
                ->constrained('todos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_previous_id');
            $table->dropColumn([
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_working_days_only',
                'recurrence_ends_on',
                'recurrence_remaining',
                'recurrence_copy_description',
            ]);
        });
    }
};
