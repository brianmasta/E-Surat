<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('letters')
            ->where('status', 'Disposisi Sementara')
            ->update(['status' => 'Disposisi Pimpinan']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('letters')
            ->where('status', 'Disposisi Pimpinan')
            ->update(['status' => 'Disposisi Sementara']);
    }
};
