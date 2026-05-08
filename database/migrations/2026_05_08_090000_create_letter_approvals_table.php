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
        Schema::create('letter_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->string('target_role');
            $table->string('status')->default('Menunggu');
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_approvals');
    }
};
