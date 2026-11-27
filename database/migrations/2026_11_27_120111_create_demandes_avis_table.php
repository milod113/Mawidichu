<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_demandeur_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('medecin_demandeur_id')->constrained('medecins')->restrictOnDelete();
            $table->foreignId('service_cible_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('medecin_cible_id')->nullable()->constrained('medecins')->restrictOnDelete();
            $table->enum('type_avis', ['senior_interne', 'inter_specialite']);
            $table->text('motif');
            $table->text('reponse_avis')->nullable();
            $table->enum('statut', ['en_attente', 'repondu'])->default('en_attente');
            // Fin de l'autorisation d'accès temporaire accordée au service cible.
            $table->dateTime('expire_le')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'service_cible_id', 'expire_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_avis');
    }
};
