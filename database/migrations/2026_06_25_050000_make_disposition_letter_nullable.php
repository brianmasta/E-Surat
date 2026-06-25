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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('dispositions', function (Blueprint $table) {
            $table->dropForeign(['letter_id']);
            $table->foreignId('letter_id')->nullable()->change();
            $table->foreign('letter_id')->references('id')->on('letters')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('dispositions', function (Blueprint $table) {
            $table->dropForeign(['letter_id']);
            $table->foreignId('letter_id')->nullable(false)->change();
            $table->foreign('letter_id')->references('id')->on('letters')->cascadeOnDelete();
        });
    }
};
