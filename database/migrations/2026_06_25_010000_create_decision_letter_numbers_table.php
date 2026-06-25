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
        Schema::create('decision_letter_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 20);
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('year');
            $table->string('number')->unique();
            $table->string('title');
            $table->date('decision_date')->nullable();
            $table->string('status', 40)->default('Dipesan');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_code', 'year', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decision_letter_numbers');
    }
};
