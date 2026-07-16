<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uchovává Passport OAuth klíče (MCP server + API) v databázi, aby přežily
     * smazání volume i redeploy. Klíče generuje admin v administraci; privátní
     * klíč se ukládá zašifrovaný (cast 'encrypted' přes APP_KEY).
     */
    public function up(): void
    {
        Schema::create('passport_keys', function (Blueprint $table) {
            $table->id();
            $table->text('private_key');
            $table->text('public_key');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passport_keys');
    }
};
