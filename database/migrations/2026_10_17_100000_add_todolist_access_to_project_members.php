<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Whether the person sees every todolist of the project; otherwise only the ones in todolist_user.
        Schema::table('project_members', function (Blueprint $table) {
            $table->boolean('all_todolists')->default(false)->after('user_id');
        });

        // People already in a project keep seeing everything they saw until now.
        DB::table('project_members')->update(['all_todolists' => true]);

        Schema::create('todolist_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todolist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['todolist_id', 'user_id']);
        });

        // A person who has no account yet; they join the project once they sign up with this e-mail.
        Schema::create('project_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->boolean('all_todolists')->default(false);
            $table->json('todolist_ids')->nullable();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_invitations');
        Schema::dropIfExists('todolist_user');

        Schema::table('project_members', function (Blueprint $table) {
            $table->dropColumn('all_todolists');
        });
    }
};
