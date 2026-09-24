<?php

use App\Models\Calculation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('calculations')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('id')
            ->each(function (object $calculation) {
                $html = Calculation::descriptionToHtml($calculation->description);

                if ($html !== $calculation->description) {
                    DB::table('calculations')->where('id', $calculation->id)->update(['description' => $html]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
