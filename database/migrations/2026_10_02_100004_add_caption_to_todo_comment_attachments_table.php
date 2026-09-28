<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_comment_attachments', function (Blueprint $table) {
            $table->string('caption')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('todo_comment_attachments', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
