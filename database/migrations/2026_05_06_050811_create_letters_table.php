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
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('unit_code', 20)->default('SET-MRP');
            $table->string('number')->unique();
            $table->string('subject');
            $table->string('external_party');
            $table->date('letter_date');
            $table->string('file_path')->nullable();
            $table->string('status', 40)->default('Baru');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
