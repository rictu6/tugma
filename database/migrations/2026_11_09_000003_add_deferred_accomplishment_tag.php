<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accomplishments', function (Blueprint $table) {
            $table->enum('tag', ['met', 'unmet', 'cont', 'moved', 'na', 'exceeded', 'deferred'])->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('accomplishments')->where('tag', 'deferred')->exists()) {
            throw new RuntimeException('Reassign deferred accomplishment tags before rolling back this migration.');
        }

        Schema::table('accomplishments', function (Blueprint $table) {
            $table->enum('tag', ['met', 'unmet', 'cont', 'moved', 'na', 'exceeded'])->nullable()->change();
        });
    }
};
