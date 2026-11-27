<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constantes_preconsultation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rdv_id')->unique()->constrained('rendez_vous')->restrictOnDelete();
            $table->foreignId('infirmier_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('tension_sys')->nullable();
            $table->unsignedSmallInteger('tension_dia')->nullable();
            $table->unsignedSmallInteger('frequence_cardiaque')->nullable();
            $table->decimal('poids', 5, 2)->nullable();   // kg
            $table->decimal('taille', 5, 1)->nullable();  // cm
            $table->decimal('temperature', 4, 1)->nullable(); // °C
            $table->decimal('glycemie', 5, 2)->nullable();    // g/L
            $table->text('notes_ecg_soins')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constantes_preconsultation');
    }
};
