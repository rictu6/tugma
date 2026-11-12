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
        Schema::create('allotments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_indicator_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('sub_allotment_number')->index();
            $table->unsignedSmallInteger('year');
            $table->string('authorization_type');
            $table->decimal('amount', 15, 2);
            $table->index(['performance_indicator_id', 'sub_allotment_number', 'year']);
            $table->timestamps();
            $table->unique(['performance_indicator_id', 'sub_allotment_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allotments');
    }
};
