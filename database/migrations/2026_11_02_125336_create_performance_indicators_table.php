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
        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('program_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('office_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('code')->index(); //reference for performance indicator code in allotment entries
            $table->text('description');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->index(['program_id', 'office_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_indicators');
    }
};
