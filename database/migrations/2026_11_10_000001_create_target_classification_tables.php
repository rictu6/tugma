<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outcome_area_target', function (Blueprint $table) {
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outcome_area_id')->constrained()->restrictOnDelete();
            $table->primary(['target_id', 'outcome_area_id']);
        });

        Schema::create('program_target', function (Blueprint $table) {
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->primary(['target_id', 'program_id']);
        });

        DB::table('program_target')->insertUsing(
            ['target_id', 'program_id'],
            DB::table('performance_indicators')->select('target_id', 'program_id')->distinct(),
        );

        DB::table('outcome_area_target')->insertUsing(
            ['target_id', 'outcome_area_id'],
            DB::table('performance_indicators')
                ->join('programs', 'programs.id', '=', 'performance_indicators.program_id')
                ->select('performance_indicators.target_id', 'programs.outcome_area_id')
                ->distinct(),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('program_target');
        Schema::dropIfExists('outcome_area_target');
    }
};
