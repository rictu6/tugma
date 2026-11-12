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
        Schema::create('accomplishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_indicator_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('type');
            $table->enum('unit', ['percentage', 'number'])->nullable();
            $table->unsignedTinyInteger('target_quarter');
            $table->unsignedTinyInteger('target_semi_annual');
            $table->date('date_accomplished')->nullable();
            $table->string('actual')->nullable();
            $table->text('actual_accomplishment_description')->nullable();
            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['performance_indicator_id', 'type', 'target_quarter', 'target_semi_annual']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accomplishments');
    }
};
