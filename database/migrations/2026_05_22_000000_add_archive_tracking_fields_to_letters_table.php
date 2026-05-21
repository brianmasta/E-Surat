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
        Schema::table('letters', function (Blueprint $table) {
            $table->string('archive_location')->nullable()->after('due_date');
            $table->string('archive_box')->nullable()->after('archive_location');
            $table->string('retention_category')->nullable()->after('archive_box');
            $table->date('retention_until')->nullable()->after('retention_category');
            $table->text('archive_notes')->nullable()->after('retention_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn([
                'archive_location',
                'archive_box',
                'retention_category',
                'retention_until',
                'archive_notes',
            ]);
        });
    }
};
