<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source IDs from Freelo, so the import can be re-run without duplicates and relations
 * (parents, mentions, attached files) can be resolved.
 */
return new class extends Migration
{
    /** @var list<string> Tables whose Freelo counterpart has an integer ID. */
    private array $tables = ['users', 'projects', 'todolists', 'todos', 'todo_comments', 'work_reports', 'invoices'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('freelo_id')->nullable()->unique();
            });
        }

        // Freelo files are identified by a UUID.
        Schema::table('todo_comment_attachments', function (Blueprint $table) {
            $table->uuid('freelo_uuid')->nullable()->unique();
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['freelo_id']);
                $table->dropColumn('freelo_id');
            });
        }

        Schema::table('todo_comment_attachments', function (Blueprint $table) {
            $table->dropUnique(['freelo_uuid']);
            $table->dropColumn('freelo_uuid');
        });
    }
};
