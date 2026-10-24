<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kanály, kterými osoba chce dostávat notifikace o úkolech.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_by_mail')->default(true);
        });

        // Osoba sleduje všechny úkoly projektu, i když u nich není řešitelem ani sledujícím.
        Schema::table('project_members', function (Blueprint $table) {
            $table->boolean('follow_all')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('project_members', fn (Blueprint $table) => $table->dropColumn('follow_all'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notify_in_app', 'notify_by_mail']));
    }
};
