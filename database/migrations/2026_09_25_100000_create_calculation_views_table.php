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
        Schema::create('calculation_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_id')->constrained()->cascadeOnDelete();
            // Set when a logged-in user opens the public link (e.g. to check it before sending).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device', 20)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('browser', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('viewed_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculation_views');
    }
};
