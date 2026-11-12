<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('accomplishments', 'target')) {
            return;
        }

        Schema::table('accomplishments', function (Blueprint $table) {
            $table->string('target')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('accomplishments', 'target')) {
            return;
        }

        throw new RuntimeException('The accomplishments.target column may contain existing data. Review and remove it manually before rolling back this migration.');
    }
};
