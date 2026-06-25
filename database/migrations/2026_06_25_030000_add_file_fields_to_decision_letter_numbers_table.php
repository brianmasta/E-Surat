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
        Schema::table('decision_letter_numbers', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('notes');
            $table->string('file_original_name')->nullable()->after('file_path');
            $table->string('file_mime_type')->nullable()->after('file_original_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decision_letter_numbers', function (Blueprint $table) {
            $table->dropColumn([
                'file_path',
                'file_original_name',
                'file_mime_type',
                'file_size',
            ]);
        });
    }
};
