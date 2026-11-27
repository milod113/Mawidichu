<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('nom_ar')->nullable();
            $table->string('code', 20)->unique();
            $table->enum('mode_planification', ['quota_vague', 'creneaux_dynamiques', 'mixte'])->default('quota_vague');
            $table->enum('mode_attribution_rdv', ['centralise', 'decentralise', 'hybride'])->default('hybride');
            $table->boolean('preconsultation_active')->default(false);
            // Ce que l'écran TV affiche : le numéro de ticket ou le nom du patient.
            $table->enum('affichage_tv_mode', ['ticket', 'nom'])->default('ticket');
            $table->unsignedSmallInteger('nb_box')->default(1);
            // Documents / bilans à ramener par défaut (liste libre, imprimée sur le ticket).
            $table->json('documents_defaut_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
