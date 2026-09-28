<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('status');
            $table->decimal('budget', 12, 2)->nullable()->after('hourly_rate');
            $table->unsignedInteger('budget_minutes')->nullable()->after('budget');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'budget', 'budget_minutes']);
        });
    }
};
