<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rdv_id')->unique()->constrained('rendez_vous')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('medecin_id')->constrained('medecins')->restrictOnDelete();
            $table->foreignId('superviseur_senior_id')->nullable()->constrained('medecins')->restrictOnDelete();
            $table->text('motif_reel')->nullable();
            $table->text('examen_clinique')->nullable();
            $table->text('diagnostic')->nullable();
            $table->text('conduite_a_tenir')->nullable();
            $table->json('ordonnance_json')->nullable();
            $table->json('bilans_demandes_json')->nullable();
            $table->unsignedSmallInteger('delai_controle_jours')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Cloisonnement : le dossier d'un patient est toujours lu par service.
            $table->index(['patient_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
