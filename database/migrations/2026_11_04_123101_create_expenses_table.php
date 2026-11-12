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
        if (DB::table('allotments')->exists() || DB::table('allotment_entries')->exists()) {
            throw new \RuntimeException('Allotments and allotment entries must be empty before restructuring them into expenses.');
        }

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allotment_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('uacs_id')
                ->constrained('uacs')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unique(['allotment_id', 'uacs_id']);
            $table->timestamps();
        });

        Schema::table('allotment_entries', function (Blueprint $table) {
            $table->foreignId('expense_id')
                ->after('id')
                ->constrained()
                ->restrictOnDelete();

            $table->dropForeign(['allotment_id']);
            $table->dropForeign(['uacs_id']);
            $table->dropIndex(['allotment_id', 'uacs_id', 'entry_date']);
            $table->dropUnique(['allotment_id', 'uacs_id', 'entry_date']);
            $table->dropColumn(['allotment_id', 'uacs_id']);

            $table->unique(['expense_id', 'entry_date']);
        });

        Schema::table('allotments', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('allotments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->default(0);
        });

        foreach (DB::table('expenses')
            ->select('allotment_id')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('allotment_id')
            ->get() as $expenseTotal) {
            DB::table('allotments')
                ->where('id', $expenseTotal->allotment_id)
                ->update(['amount' => $expenseTotal->total_amount]);
        }

        Schema::table('allotment_entries', function (Blueprint $table) {
            $table->foreignId('allotment_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('uacs_id')
                ->nullable()
                ->after('allotment_id')
                ->constrained('uacs')
                ->restrictOnDelete();
        });

        foreach (DB::table('allotment_entries')
            ->join('expenses', 'expenses.id', '=', 'allotment_entries.expense_id')
            ->select('allotment_entries.id', 'expenses.allotment_id', 'expenses.uacs_id')
            ->get() as $entry) {
            DB::table('allotment_entries')
                ->where('id', $entry->id)
                ->update([
                    'allotment_id' => $entry->allotment_id,
                    'uacs_id' => $entry->uacs_id,
                ]);
        }

        Schema::table('allotment_entries', function (Blueprint $table) {
            $table->dropForeign(['expense_id']);
            $table->dropUnique(['expense_id', 'entry_date']);
            $table->dropColumn('expense_id');
            $table->index(['allotment_id', 'uacs_id', 'entry_date']);
            $table->unique(['allotment_id', 'uacs_id', 'entry_date']);
        });

        Schema::dropIfExists('expenses');
    }
};
