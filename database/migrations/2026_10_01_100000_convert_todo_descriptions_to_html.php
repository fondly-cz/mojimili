<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Todo descriptions are now written in the rich editor and rendered as HTML,
     * so existing plain-text descriptions are converted to keep their line breaks.
     */
    public function up(): void
    {
        DB::table('todos')
            ->whereNotNull('description')
            ->orderBy('id')
            ->each(function (object $todo) {
                DB::table('todos')
                    ->where('id', $todo->id)
                    ->update(['description' => RichText::toHtml($todo->description)]);
            });
    }

    /**
     * The HTML keeps the original text, so there is nothing to undo.
     */
    public function down(): void {}
};
