<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files attached to the todo's description, as todo_comment_attachments are to its comments.
        Schema::create('todo_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            // Path on the private "local" disk; files are served only through the app.
            $table->string('path');
            $table->string('original_name');
            $table->string('caption')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_attachments');
    }
};
