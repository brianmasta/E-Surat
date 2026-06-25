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
        Schema::table('dispositions', function (Blueprint $table) {
            $table->string('input_method', 40)->default('Elektronik')->after('status');
            $table->string('input_by_name')->nullable()->after('input_method');
            $table->string('input_by_role')->nullable()->after('input_by_name');
            $table->string('scan_path')->nullable()->after('input_by_role');
            $table->string('scan_original_name')->nullable()->after('scan_path');
            $table->string('scan_mime_type')->nullable()->after('scan_original_name');
            $table->unsignedBigInteger('scan_size')->nullable()->after('scan_mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->dropColumn([
                'input_method',
                'input_by_name',
                'input_by_role',
                'scan_path',
                'scan_original_name',
                'scan_mime_type',
                'scan_size',
            ]);
        });
    }
};
