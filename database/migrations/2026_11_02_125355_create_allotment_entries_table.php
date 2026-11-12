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
        Schema::create('allotment_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allotment_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('uacs_id')
                ->constrained('uacs')
                ->restrictOnDelete();

            $table->string('performance_indicator_code')->nullable(); //refers to performance indicator code

            $table->date('entry_date');
            $table->decimal('amount', 15, 2);
            $table->index(['allotment_id', 'uacs_id', 'entry_date']);
            $table->unique(['allotment_id', 'uacs_id', 'entry_date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allotment_entries');
    }
};
