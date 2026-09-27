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
        Schema::create('todo_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Shown instead of the user for posts imported from elsewhere (e.g. Freelo) by people without a CRM account.
            $table->string('author_name')->nullable();
            // Sanitized HTML from the rich editor; may be empty when the post only carries files.
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->index(['todo_id', 'created_at']);
        });

        Schema::create('todo_comment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_comment_id')->constrained()->cascadeOnDelete();
            // Path on the private "local" disk; files are served only through the app.
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('todo_comment_attachments');
        Schema::dropIfExists('todo_comments');
    }
};
