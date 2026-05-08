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
            $table->string('agenda_number')->nullable()->unique()->after('classification_code');
            $table->date('received_date')->nullable()->after('letter_date');
            $table->string('nature')->default('Biasa')->after('received_date');
            $table->string('urgency')->default('Normal')->after('nature');
            $table->date('due_date')->nullable()->after('urgency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropUnique(['agenda_number']);
            $table->dropColumn([
                'agenda_number',
                'received_date',
                'nature',
                'urgency',
                'due_date',
            ]);
        });
    }
};
