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
            $table->longText('outgoing_body')->nullable()->after('subject');
            $table->string('signer_name')->nullable()->after('outgoing_body');
            $table->string('signer_title')->nullable()->after('signer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn([
                'outgoing_body',
                'signer_name',
                'signer_title',
            ]);
        });
    }
};
